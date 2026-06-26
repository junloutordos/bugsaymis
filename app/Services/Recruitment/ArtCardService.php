<?php

namespace App\Services\Recruitment;

use App\Models\JobItem;
use Illuminate\Support\Facades\Storage;

/**
 * Generates "art card" images (Cover + Detail) for a job posting, used by HR
 * to post on social media / the website.
 *
 * Rendering strategy: the cards are the campus's hand-designed Photoshop
 * templates (illustrations, badges, header/footer, EEO disclaimer, the baked
 * "Campus Director" submit-to block, etc.) shipped as 2540x2540 PNGs. We load
 * a template as the base canvas and STAMP only the dynamic text (position list,
 * qualifications, competencies, salary, deadline) onto the pre-placed empty
 * regions with GD using the brand's Montserrat fonts. No redraw-from-scratch,
 * no external image libraries or binaries.
 *
 * Coordinates below are in the template's native 2540px space and were
 * calibrated against the supplied filled reference cards (SST II-V, July 2026).
 */
class ArtCardService
{
    private const NAVY  = [13, 27, 64];
    private const GOLD  = [255, 199, 44];
    private const WHITE = [255, 255, 255];

    // Matches the PLANTILLA_NAMES list in resources/js/Pages/Recruitment/JobItems/Index.vue
    // so the art card and the form agree on which positions are "plantilla".
    private const PLANTILLA_TYPE_NAMES = ['Plantilla Teaching', 'Plantilla Non-Teaching'];

    private string $fontBold;
    private string $fontExtraBold;
    private string $fontRegular;
    private string $fontItalic;

    private string $coverTemplate;
    private string $detailTemplate;

    public function __construct()
    {
        $this->fontBold      = storage_path('fonts/Montserrat-Bold.ttf');
        $this->fontExtraBold = storage_path('fonts/Montserrat-ExtraBold.ttf');
        $this->fontRegular   = storage_path('fonts/Montserrat-Regular.ttf');
        $this->fontItalic    = storage_path('fonts/Montserrat-Italic.ttf');

        // Kept under storage/ (a tracked path, like storage/fonts) — NOT under
        // storage/app, which is gitignored and would never reach production.
        $base = storage_path('art-card-templates');
        $this->coverTemplate  = "{$base}/plantilla-cover.png";
        $this->detailTemplate = "{$base}/plantilla-detail.png";
    }

    /**
     * Build both cards and upload them to S3. Updates art_card_generated_at.
     */
    public function generate(JobItem $jobItem): void
    {
        $jobItem->loadMissing(['office', 'requirements', 'jobVacancies', 'recruitmentType', 'plantillaNumbers']);

        // Two 2540x2540 truecolor canvases + PNG template decode needs far more
        // headroom than the default CLI/web memory_limit.
        $previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $cover  = $this->renderToJpeg($this->buildCoverCard($jobItem));
            $detail = $this->renderToJpeg($this->buildDetailCard($jobItem));
        } finally {
            ini_set('memory_limit', $previousMemoryLimit);
        }

        Storage::disk('s3')->put($this->coverPath($jobItem), $cover);
        Storage::disk('s3')->put($this->detailPath($jobItem), $detail);

        $jobItem->forceFill(['art_card_generated_at' => now()])->save();
    }

    public function coverPath(JobItem $jobItem): string
    {
        return "recruitment/art-cards/{$jobItem->id}/cover.jpg";
    }

    public function detailPath(JobItem $jobItem): string
    {
        return "recruitment/art-cards/{$jobItem->id}/detail.jpg";
    }

    // ── Card builders ────────────────────────────────────────────────────────

    private function buildCoverCard(JobItem $jobItem): \GdImage
    {
        $im   = $this->loadTemplate($this->coverTemplate);
        $white = $this->color($im, self::WHITE);
        $gold  = $this->color($im, self::GOLD);
        $navy  = $this->color($im, self::NAVY);

        // "PLANTILLA POSITION" (or duration type) label above the list.
        $this->text($im, $this->fontItalic, 38, 152, 1012, $gold, $this->badgeLabel($jobItem));

        // Grouped position list — each line "<count> - <TITLE>".
        $positions = $this->coverPositions($jobItem);
        $lines = array_map(fn ($p) => "{$p['count']} - {$p['title']}", $positions);
        // Size so each position stays on ONE line within the band left of the
        // illustration (y 1058 .. 1500), above the baked submit-to block.
        [$size, $lineH, $fitted] = $this->fitListNoWrap($this->fontExtraBold, $lines, 1030, 442, 54, 28);
        $y = 1058 + (int) $size;
        foreach ($fitted as $line) {
            $this->text($im, $this->fontExtraBold, $size, 152, $y, $white, $line);
            $y += (int) $lineH;
        }

        // Deadline date inside the baked gold box (x 210-1245, y 1980-2128).
        $this->textCenteredIn($im, $this->fontExtraBold, 46, $this->deadlineText($jobItem), 210, 1245, 2108, $navy, 30);

        return $im;
    }

    private function buildDetailCard(JobItem $jobItem): \GdImage
    {
        $im    = $this->loadTemplate($this->detailTemplate);
        $white = $this->color($im, self::WHITE);
        $gold  = $this->color($im, self::GOLD);
        $navy  = $this->color($im, self::NAVY);

        $isPlantilla = $this->isPlantilla($jobItem);

        // ── Gold title bar (x 228-1752, y 330-518) ──
        $count = $this->itemCount($jobItem);
        $title = "{$count} - " . strtoupper($jobItem->position_title);
        [$tSize, , $tLines] = $this->fitLines($this->fontExtraBold, [$title], 1430, 130, 92, 46);
        $this->text($im, $this->fontExtraBold, $tSize, 292, 456, $navy, $tLines[0]);
        $subtitle = $isPlantilla ? $this->plantillaSubtitle($jobItem) : $jobItem->office?->name;
        if ($subtitle) {
            $sSize = $this->shrinkToWidth($this->fontItalic, 36, $subtitle, 1440, 20);
            $this->text($im, $this->fontItalic, $sSize, 295, 506, $navy, $subtitle);
        }

        // ── Navy compensation box (x 1757-2411, y 330-518) ──
        $comp = $isPlantilla
            ? ('SG' . ($jobItem->salary_grade ?? '—') . ' - ' . $this->money($jobItem->monthly_salary))
            : ($this->money($jobItem->daily_rate) . '/day');
        $this->textCenteredIn($im, $this->fontExtraBold, 54, $comp, 1757, 2411, 432, $gold, 30);

        // ── MINIMUM QUALIFICATIONS values (labels are baked into the template) ──
        // Each value is stamped beside its baked label; the long Education value
        // wraps into the reserved gap and auto-shrinks so it never collides with
        // the Experience label below it.
        if ($isPlantilla) {
            $this->drawValue($im, $jobItem->education,   540, 684, 295, 2400, 150, 36, $white); // wraps full width
            $this->drawValue($im, $jobItem->experience,  560, 833, 560, 2420, 0,   34, $white); // single line
            $this->drawValue($im, $jobItem->training,    505, 884, 505, 2420, 0,   34, $white);
            $this->drawValue($im, $jobItem->eligibility, 525, 940, 525, 2420, 0,   34, $white);
        } else {
            $quals = $jobItem->qualification_standards
                ?: trim(($jobItem->education ?? '') . ' ' . ($jobItem->experience ?? ''));
            $this->drawValue($im, $quals, 540, 684, 295, 2400, 290, 36, $white);
        }

        // ── COMPETENCIES / DUTIES (two-column numbered list under baked heading) ──
        $items = $isPlantilla
            ? $this->competencyLines($jobItem)
            : array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $jobItem->duties_responsibilities))));
        $this->drawTwoColumnList($im, $items, 1145, 370, $white);

        // ── Deadline date (to the right of the baked gold box) ──
        $deadline = $this->deadlineParts($jobItem);
        $this->text($im, $this->fontExtraBold, 44, 905, 2098, $navy, $deadline[0]);
        if (! empty($deadline[1])) {
            $this->text($im, $this->fontExtraBold, 44, 905, 2168, $navy, $deadline[1]);
        }

        return $im;
    }

    // ── Content helpers ──────────────────────────────────────────────────────

    private function badgeLabel(JobItem $jobItem): string
    {
        return $this->isPlantilla($jobItem)
            ? 'PLANTILLA POSITION'
            : strtoupper(str_replace('_', ' ', $jobItem->duration_type ?? $jobItem->recruitmentType?->name ?? 'JOB ORDER'));
    }

    /**
     * The cover lists every job item in the same "posting" — published with the
     * same recruitment type AND the same closing date. A draft with no vacancy
     * yet just lists itself (deadline shows "TO BE ANNOUNCED" until publish).
     */
    private function coverPositions(JobItem $jobItem): array
    {
        $closing = $jobItem->jobVacancies->sortByDesc('posting_date')->first()?->closing_date;

        if ($closing) {
            $group = JobItem::query()
                ->where('recruitment_type_id', $jobItem->recruitment_type_id)
                ->whereHas('jobVacancies', fn ($q) => $q->whereDate('closing_date', $closing->toDateString()))
                ->with('plantillaNumbers')
                ->get();
            if (! $group->contains('id', $jobItem->id)) {
                $group->push($jobItem);
            }
        } else {
            $group = collect([$jobItem]);
        }

        return $group
            ->sortByDesc(fn ($j) => (int) $j->salary_grade)
            ->map(fn ($j) => [
                'count' => $this->itemCount($j),
                'title' => strtoupper($j->position_title),
            ])
            ->values()
            ->all();
    }

    /** Vacant plantilla item-number count, falling back to total, then 1. */
    private function itemCount(JobItem $jobItem): int
    {
        $numbers = $jobItem->plantillaNumbers ?? collect();
        $vacant  = $numbers->where('status', 'vacant')->count();

        return max(1, $vacant ?: $numbers->count());
    }

    private function competencyLines(JobItem $jobItem): array
    {
        return collect($jobItem->competencies ?? [])
            ->map(function ($c) {
                if (is_array($c)) {
                    $name  = trim($c['name'] ?? '');
                    $level = trim($c['level'] ?? '');
                    return $level ? "{$name} - " . ucfirst($level) : $name;
                }
                return trim((string) $c);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function isPlantilla(JobItem $jobItem): bool
    {
        $typeName = $jobItem->recruitmentType?->name;
        if ($typeName) {
            return in_array($typeName, self::PLANTILLA_TYPE_NAMES, true);
        }

        return ! is_null($jobItem->salary_grade);
    }

    private function plantillaSubtitle(JobItem $jobItem): ?string
    {
        $numbers = $jobItem->plantillaNumbers->pluck('plantilla_item_no');
        if ($numbers->isEmpty()) {
            return null;
        }
        if ($numbers->count() === 1) {
            return "Plantilla Item No.: {$numbers->first()}";
        }
        if ($numbers->count() === 2) {
            return "Plantilla Item No.: {$numbers->implode(' and ')}";
        }

        $preview = $numbers->take(3)->implode(', ');

        return "Plantilla Item Nos.: {$preview} +" . ($numbers->count() - 3) . ' more';
    }

    private function deadlineText(JobItem $jobItem): string
    {
        $vacancy = $jobItem->jobVacancies->sortByDesc('posting_date')->first();
        if (! $vacancy || ! $vacancy->closing_date) {
            return 'TO BE ANNOUNCED';
        }

        return strtoupper($vacancy->closing_date->format('F j, Y')) . ', 5:00 PM';
    }

    /** Two-part deadline for the detail card: ["JULY 6, 2026", "5:00 PM"]. */
    private function deadlineParts(JobItem $jobItem): array
    {
        $vacancy = $jobItem->jobVacancies->sortByDesc('posting_date')->first();
        if (! $vacancy || ! $vacancy->closing_date) {
            return ['TO BE', 'ANNOUNCED'];
        }

        return [strtoupper($vacancy->closing_date->format('F j, Y')), '5:00 PM'];
    }

    private function money(?float $amount): string
    {
        return 'P' . number_format((float) $amount, 2);
    }

    // ── Canvas primitives ────────────────────────────────────────────────────

    private function loadTemplate(string $path): \GdImage
    {
        $im = @imagecreatefrompng($path);
        if (! $im) {
            throw new \RuntimeException("Art card template missing or unreadable: {$path}");
        }

        return $im;
    }

    private function color(\GdImage $im, array $rgb): int
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    /** Draw left-aligned text at an absolute baseline. */
    private function text(\GdImage $im, string $font, float $size, int $x, int $baseline, int $color, string $text): void
    {
        if ($text === '') {
            return;
        }
        imagettftext($im, $size, 0, $x, $baseline, $color, $font, $text);
    }

    /** Draw text horizontally centered within [x0,x1] at a given baseline. */
    private function textCenteredIn(\GdImage $im, string $font, float $size, string $text, int $x0, int $x1, int $baseline, int $color, int $padding = 0): void
    {
        if ($text === '') {
            return;
        }
        $maxW = ($x1 - $x0) - 2 * $padding;
        $size = $this->shrinkToWidth($font, $size, $text, $maxW, 18);
        $box  = imagettfbbox($size, 0, $font, $text);
        $w    = $box[2] - $box[0];
        $x    = $x0 + (int) ((($x1 - $x0) - $w) / 2);
        imagettftext($im, $size, 0, $x, $baseline, $color, $font, $text);
    }

    /**
     * Stamp a value next to a baked label. If $reservedHeight > 0 the value
     * wraps (continuation lines flow to $wrapX) and the font auto-shrinks to
     * fit; otherwise it is a single line shrunk to fit the available width.
     */
    private function drawValue(\GdImage $im, ?string $value, int $x, int $baseline, int $wrapX, int $rightEdge, int $reservedHeight, float $size, int $color): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        // Single-line field (Experience / Training / Eligibility).
        if ($reservedHeight <= 0) {
            $maxW = $rightEdge - $x;
            $size = $this->shrinkToWidth($this->fontBold, $size, $value, $maxW, 24);
            $this->text($im, $this->fontBold, $size, $x, $baseline, $color, $value);

            return;
        }

        // Wrapping field (Education). First line begins after the label at $x;
        // continuation lines start at the left margin $wrapX.
        for ($s = $size; $s >= 26; $s -= 1) {
            $lineH = $s * 1.22;
            $lines = $this->wrapFirstThenMargin($this->fontBold, $s, $value, $x, $wrapX, $rightEdge);
            if (count($lines) * $lineH <= $reservedHeight) {
                break;
            }
        }
        $y = $baseline;
        foreach ($lines as $i => $line) {
            $lx = $i === 0 ? $x : $wrapX;
            $this->text($im, $this->fontBold, $s, $lx, (int) $y, $color, $line);
            $y += $lineH;
        }
    }

    /**
     * Two-column numbered list (competencies / duties). Splits items in half,
     * auto-shrinks the font so the taller column fits the reserved height.
     */
    private function drawTwoColumnList(\GdImage $im, array $items, int $topBaseline, int $reservedHeight, int $color): void
    {
        $items = array_values($items);
        if (empty($items)) {
            return;
        }

        $half = (int) ceil(count($items) / 2);
        $cols = [
            ['x' => 300,  'numW' => 60, 'right' => 1480, 'items' => array_slice($items, 0, $half)],
            ['x' => 1561, 'numW' => 60, 'right' => 2470, 'items' => array_slice($items, $half)],
        ];

        for ($size = 30; $size >= 18; $size -= 1) {
            $lineH = $size * 1.55;
            $fits  = true;
            foreach ($cols as $col) {
                $rows = 0;
                foreach ($col['items'] as $i => $txt) {
                    $rows += count($this->wrapText($this->fontBold, $size, $this->numberPrefix($i, $col, $half) . $txt, $col['right'] - $col['x']));
                }
                if ($rows * $lineH > $reservedHeight) {
                    $fits = false;
                    break;
                }
            }
            if ($fits) {
                break;
            }
        }

        $lineH = $size * 1.55;
        foreach ($cols as $col) {
            $y = $topBaseline;
            foreach ($col['items'] as $i => $txt) {
                $num   = $this->numberPrefix($i, $col, $half);
                $body  = $this->wrapText($this->fontBold, $size, $num . $txt, $col['right'] - $col['x']);
                foreach ($body as $li => $line) {
                    // First wrapped line keeps the number; continuation indents.
                    $lx = $li === 0 ? $col['x'] : $col['x'] + $col['numW'];
                    $draw = $li === 0 ? $line : ltrim($line);
                    $this->drawCompetencyLine($im, $size, $lx, (int) $y, $color, $draw, $li === 0);
                    $y += $lineH;
                }
            }
        }
    }

    private function numberPrefix(int $i, array $col, int $half): string
    {
        $n = $col['x'] < 1000 ? $i + 1 : $half + $i + 1;

        return "{$n}. ";
    }

    /** Draw "N. Name - Level" with the "- Level" portion in italic. */
    private function drawCompetencyLine(\GdImage $im, float $size, int $x, int $baseline, int $color, string $text, bool $hasNumber): void
    {
        $dashPos = strrpos($text, ' - ');
        if ($dashPos === false) {
            $this->text($im, $this->fontBold, $size, $x, $baseline, $color, $text);

            return;
        }

        $main = substr($text, 0, $dashPos);
        $tail = substr($text, $dashPos);
        $this->text($im, $this->fontBold, $size, $x, $baseline, $color, $main);
        $box = imagettfbbox($size, 0, $this->fontBold, $main);
        $this->text($im, $this->fontItalic, $size, $x + ($box[2] - $box[0]), $baseline, $color, $tail);
    }

    // ── Text measurement / wrapping ──────────────────────────────────────────

    private function shrinkToWidth(string $font, float $size, string $text, float $maxWidth, float $minSize): float
    {
        for ($s = $size; $s >= $minSize; $s -= 1) {
            $box = imagettfbbox($s, 0, $font, $text);
            if (($box[2] - $box[0]) <= $maxWidth) {
                return $s;
            }
        }

        return $minSize;
    }

    private function wrapText(string $font, float $size, string $text, float $maxWidth): array
    {
        $words   = preg_split('/\s+/', trim($text));
        $lines   = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            $box       = imagettfbbox($size, 0, $font, $candidate);
            if (($box[2] - $box[0]) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    /**
     * Wrap text where the first line starts at $firstX and continuation lines
     * start at $marginX (used for "Label: value …" where value wraps under the
     * label). Both share the same $rightEdge.
     */
    private function wrapFirstThenMargin(string $font, float $size, string $text, int $firstX, int $marginX, int $rightEdge): array
    {
        $words   = preg_split('/\s+/', trim($text));
        $lines   = [];
        $current = '';
        $maxW    = $rightEdge - $firstX; // first line width
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            $box       = imagettfbbox($size, 0, $font, $candidate);
            if (($box[2] - $box[0]) > $maxW && $current !== '') {
                $lines[] = $current;
                $current = $word;
                $maxW    = $rightEdge - $marginX; // subsequent lines are wider
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    /**
     * Fit a set of single-line strings into a band: pick the largest font
     * (down to $minSize) whose total height fits $maxHeight, wrapping each
     * line if needed.
     */
    private function fitLines(string $font, array $lines, float $maxWidth, float $maxHeight, float $maxSize, float $minSize): array
    {
        for ($size = $maxSize; $size >= $minSize; $size -= 1) {
            $lineH      = $size * 1.32;
            $wrapped    = [];
            foreach ($lines as $line) {
                foreach ($this->wrapText($font, $size, $line, $maxWidth) as $w) {
                    $wrapped[] = $w;
                }
            }
            if (count($wrapped) * $lineH <= $maxHeight || $size <= $minSize) {
                return [$size, $lineH, $wrapped];
            }
        }

        return [$minSize, $minSize * 1.32, $lines];
    }

    /**
     * Size a list so each entry fits on a single line within $maxWidth and the
     * whole block fits $maxHeight. Falls back to wrapping at $minSize only if a
     * single entry can't fit even then.
     */
    private function fitListNoWrap(string $font, array $lines, float $maxWidth, float $maxHeight, float $maxSize, float $minSize): array
    {
        for ($size = $maxSize; $size >= $minSize; $size -= 1) {
            $lineH  = $size * 1.34;
            $widest = 0;
            foreach ($lines as $line) {
                $box    = imagettfbbox($size, 0, $font, $line);
                $widest = max($widest, $box[2] - $box[0]);
            }
            if ($widest <= $maxWidth && count($lines) * $lineH <= $maxHeight) {
                return [$size, $lineH, $lines];
            }
        }

        $lineH   = $minSize * 1.34;
        $wrapped = [];
        foreach ($lines as $line) {
            foreach ($this->wrapText($font, $minSize, $line, $maxWidth) as $w) {
                $wrapped[] = $w;
            }
        }

        return [$minSize, $lineH, $wrapped];
    }

    private function renderToJpeg(\GdImage $im): string
    {
        ob_start();
        imagejpeg($im, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }
}
