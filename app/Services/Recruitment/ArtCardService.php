<?php

namespace App\Services\Recruitment;

use App\Models\JobItem;
use Illuminate\Support\Facades\Storage;

/**
 * Generates "art card" images (Cover + Detail) for a job posting, used by HR
 * to post on social media / the website. Rendered with GD (no external image
 * libraries or binaries) using the brand's Montserrat fonts and logo already
 * shipped in this repo. Layout is a simplified, on-brand design — not a pixel
 * clone of the hand-made Photoshop templates in Documents/Hiring.
 */
class ArtCardService
{
    private const WIDTH  = 1080;
    private const HEIGHT = 1080;

    private const NAVY  = [13, 27, 64];
    private const BLUE  = [30, 80, 210];
    private const GOLD  = [255, 199, 44];
    private const WHITE = [255, 255, 255];

    private const HEADER_LINES = [
        'Department of Science and Technology',
        'PHILIPPINE SCIENCE HIGH SCHOOL',
        'CARAGA REGION CAMPUS IN BUTUAN CITY',
    ];

    private const FOOTER_LINE = 'ocd@crc.pshs.edu.ph   |   crc.pshs.edu.ph   |   #IAMPISAYCaraga';

    // Matches the PLANTILLA_NAMES list in resources/js/Pages/Recruitment/JobItems/Index.vue
    // so the art card and the form agree on which positions are "plantilla".
    private const PLANTILLA_TYPE_NAMES = ['Plantilla Teaching', 'Plantilla Non-Teaching'];

    private string $fontBold;
    private string $fontExtraBold;
    private string $fontRegular;
    private string $fontItalic;

    public function __construct()
    {
        $this->fontBold      = storage_path('fonts/Montserrat-Bold.ttf');
        $this->fontExtraBold = storage_path('fonts/Montserrat-ExtraBold.ttf');
        $this->fontRegular   = storage_path('fonts/Montserrat-Regular.ttf');
        $this->fontItalic    = storage_path('fonts/Montserrat-Italic.ttf');
    }

    /**
     * Build both cards and upload them to S3. Updates art_card_generated_at.
     */
    public function generate(JobItem $jobItem): void
    {
        $jobItem->loadMissing(['office', 'requirements', 'jobVacancies', 'recruitmentType', 'plantillaNumbers']);

        // Decoding the logo PNG + two 1080x1080 truecolor canvases needs more
        // headroom than the default CLI/web memory_limit.
        $previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

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
        $im = $this->newCanvas();
        $this->drawLogoAndHeader($im);

        $white = $this->color($im, self::WHITE);
        $navy  = $this->color($im, self::NAVY);
        $gold  = $this->color($im, self::GOLD);

        imagefilledrectangle($im, 60, 190, 540, 240, $navy);
        imagettftext($im, 24, 0, 82, 224, $white, $this->fontExtraBold, "WE'RE");
        imagefilledrectangle($im, 60, 240, 540, 310, $gold);
        imagettftext($im, 38, 0, 82, 292, $navy, $this->fontExtraBold, 'HIRING');

        $this->drawCentered($im, $this->fontExtraBold, 28, 'JOIN US', 880, 230, $white);
        $this->drawCentered($im, $this->fontExtraBold, 28, 'NOW!', 880, 268, $white);

        $isPlantilla = $this->isPlantilla($jobItem);
        $badgeLabel  = $isPlantilla
            ? 'PLANTILLA POSITION'
            : strtoupper(str_replace('_', ' ', $jobItem->duration_type ?? 'outsourced'));

        $y = 380;
        imagettftext($im, 16, 0, 60, $y, $gold, $this->fontBold, $badgeLabel);

        $y += 20;
        [$size, $lineHeight, $titleLines] = $this->fitText($this->fontExtraBold, $jobItem->position_title, 820, 130, 34, 20);
        $y += $size;
        $y = $this->drawParagraph($im, $this->fontExtraBold, $size, $lineHeight, $titleLines, 60, $y, $white);

        $subtitle = $isPlantilla
            ? $this->plantillaSubtitle($jobItem)
            : $jobItem->office?->name;
        if ($subtitle) {
            $y += 6;
            imagettftext($im, 16, 0, 60, $y, $white, $this->fontItalic, $subtitle);
        }

        $y = 700;
        $y = $this->drawSubmitToBlock($im, $jobItem, 60, $y, $white);

        $this->drawDeadlineBadge($im, $jobItem, 60, self::HEIGHT - 170, 600, $gold, $navy);
        $this->drawFooter($im);

        return $im;
    }

    private function buildDetailCard(JobItem $jobItem): \GdImage
    {
        $im = $this->newCanvas();
        $this->drawLogoAndHeader($im);

        $white = $this->color($im, self::WHITE);
        $navy  = $this->color($im, self::NAVY);
        $gold  = $this->color($im, self::GOLD);

        $isPlantilla = $this->isPlantilla($jobItem);

        // Position + compensation badges
        $y = 185;
        [$size, , $titleLines] = $this->fitText($this->fontExtraBold, '1 - ' . strtoupper($jobItem->position_title), 620, 70, 22, 14);
        $badgeH = max(60, (int) (count($titleLines) * $size * 1.4) + 20);
        imagefilledrectangle($im, 60, $y, 700, $y + $badgeH, $gold);
        $this->drawParagraph($im, $this->fontExtraBold, $size, $size * 1.4, $titleLines, 80, $y + $size + 8, $navy);

        $compText = $isPlantilla
            ? ('SG ' . ($jobItem->salary_grade ?? '—') . ' - ' . $this->money($jobItem->monthly_salary) . '/month')
            : ($this->money($jobItem->daily_rate) . '/day');
        imagefilledrectangle($im, 720, $y, 1020, $y + $badgeH, $navy);
        $this->drawCenteredBox($im, $this->fontBold, 16, $compText, 720, $y, 300, $badgeH, $white);

        $y += $badgeH + 30;

        // Section 1: qualifications
        imagettftext($im, 19, 0, 60, $y, $gold, $this->fontExtraBold, $isPlantilla ? 'MINIMUM QUALIFICATIONS' : 'QUALIFICATIONS');
        $y += 14;

        if ($isPlantilla) {
            $quals = array_filter([
                $jobItem->education   ? 'Education: ' . $jobItem->education     : null,
                $jobItem->experience  ? 'Experience: ' . $jobItem->experience    : null,
                $jobItem->training    ? 'Training: ' . $jobItem->training        : null,
                $jobItem->eligibility ? 'Eligibility: ' . $jobItem->eligibility  : null,
            ]);
            $y = $this->drawFittedParagraphList($im, $this->fontRegular, $quals, 60, $y, 960, 170, 17, 12, $white);
        } else {
            $text = $jobItem->qualification_standards ?: trim(($jobItem->education ?? '') . ' ' . ($jobItem->experience ?? ''));
            $y = $this->drawFittedBulletOrParagraph($im, $this->fontRegular, $text, 60, $y, 960, 170, 17, 12, $white);
        }

        $y += 25;

        // Section 2: competencies (plantilla) or duties & responsibilities (outsourced)
        if ($isPlantilla) {
            imagettftext($im, 19, 0, 60, $y, $gold, $this->fontExtraBold, 'COMPETENCIES REQUIRED');
            $y += 14;
            $items = collect($jobItem->competencies ?? [])
                ->map(fn ($c) => is_array($c) ? trim(($c['name'] ?? '') . ($c['level'] ? ' — ' . ucfirst($c['level']) : '')) : (string) $c)
                ->filter()
                ->values()
                ->all();
            $y = $this->drawFittedBulletList($im, $this->fontRegular, $items, 60, $y, 960, 150, 16, 11, $white);
        } else {
            imagettftext($im, 19, 0, 60, $y, $gold, $this->fontExtraBold, 'DUTIES & RESPONSIBILITIES');
            $y += 14;
            $items = array_values(array_filter(preg_split('/\r?\n/', (string) $jobItem->duties_responsibilities)));
            $y = $this->drawFittedBulletList($im, $this->fontRegular, $items, 60, $y, 960, 150, 16, 11, $white);
        }

        $y += 20;

        // Contract period (outsourced only)
        if (! $isPlantilla && $jobItem->contract_start_date && $jobItem->contract_end_date) {
            imagefilledrectangle($im, 60, $y, 1020, $y + 44, $navy);
            $period = 'CONTRACT PERIOD: ' . strtoupper($jobItem->contract_start_date->format('M d')) . ' TO ' . strtoupper($jobItem->contract_end_date->format('M d, Y'));
            $this->drawCenteredBox($im, $this->fontBold, 16, $period, 60, $y, 960, 44, $white);
            $y += 60;
        }

        // Requirements
        imagettftext($im, 17, 0, 60, $y, $gold, $this->fontExtraBold, 'REQUIREMENTS TO SUBMIT');
        $y += 12;
        $reqs = $jobItem->requirements
            ->sortByDesc(fn ($r) => $r->pivot->is_mandatory)
            ->map(fn ($r) => $r->name . ($r->pivot->is_mandatory ? '' : ' (if any)'))
            ->values()
            ->all();
        $y = $this->drawFittedBulletList($im, $this->fontRegular, $reqs, 60, $y, 960, 95, 14, 11, $white);

        $y += 15;
        $y = $this->drawSubmitToBlock($im, $jobItem, 60, $y, $white, compact: true);

        $this->drawDeadlineBadge($im, $jobItem, 660, self::HEIGHT - 130, 420, $gold, $navy);
        $this->drawFooter($im);

        return $im;
    }

    // ── Shared content blocks ───────────────────────────────────────────────

    private function drawSubmitToBlock(\GdImage $im, JobItem $jobItem, int $x, int $y, int $white, bool $compact = false): int
    {
        imagettftext($im, 15, 0, $x, $y, $white, $this->fontRegular, 'Submit your application to');
        $y += 26;
        $name = $jobItem->submit_to_name ?: 'HR Management Office';
        imagettftext($im, 19, 0, $x, $y, $white, $this->fontBold, $name);
        $y += 24;
        if ($jobItem->submit_to_title) {
            imagettftext($im, 14, 0, $x, $y, $white, $this->fontRegular, $jobItem->submit_to_title);
            $y += 20;
        }
        if (! $compact) {
            imagettftext($im, 14, 0, $x, $y, $white, $this->fontRegular, 'Philippine Science High School - Caraga Region Campus');
            $y += 20;
        }
        imagettftext($im, 14, 0, $x, $y, $white, $this->fontRegular, 'Email: hr@crc.pshs.edu.ph');
        $y += 20;

        return $y;
    }

    private function drawDeadlineBadge(\GdImage $im, JobItem $jobItem, int $x, int $y, int $w, int $gold, int $navy): void
    {
        imagefilledrectangle($im, $x, $y, $x + $w, $y + 70, $gold);
        imagettftext($im, 13, 0, $x + 20, $y + 25, $navy, $this->fontBold, 'Deadline for submission of application is on');
        imagettftext($im, 19, 0, $x + 20, $y + 53, $navy, $this->fontExtraBold, $this->deadlineText($jobItem));
    }

    // ── Data helpers ─────────────────────────────────────────────────────────

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

        $preview = $numbers->take(3)->implode(', ');
        $extra   = $numbers->count() > 3 ? ' +' . ($numbers->count() - 3) . ' more' : '';

        return "Plantilla Item Nos.: {$preview}{$extra}";
    }

    private function deadlineText(JobItem $jobItem): string
    {
        $vacancy = $jobItem->jobVacancies->sortByDesc('posting_date')->first();
        if (! $vacancy || ! $vacancy->closing_date) {
            return 'TO BE ANNOUNCED';
        }

        return strtoupper($vacancy->closing_date->format('M d, Y')) . ', 5:00 PM';
    }

    private function money(?float $amount): string
    {
        return 'P' . number_format((float) $amount, 2);
    }

    // ── Canvas primitives ────────────────────────────────────────────────────

    private function newCanvas(): \GdImage
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / (self::HEIGHT - 1);
            $color = imagecolorallocate(
                $im,
                (int) round(self::BLUE[0] + (self::NAVY[0] - self::BLUE[0]) * $ratio),
                (int) round(self::BLUE[1] + (self::NAVY[1] - self::BLUE[1]) * $ratio),
                (int) round(self::BLUE[2] + (self::NAVY[2] - self::BLUE[2]) * $ratio)
            );
            imageline($im, 0, $y, self::WIDTH, $y, $color);
        }

        return $im;
    }

    private function color(\GdImage $im, array $rgb): int
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function drawLogoAndHeader(\GdImage $im): void
    {
        $logoPath = public_path('pshslogo.png');
        if (is_file($logoPath)) {
            $logo = imagecreatefrompng($logoPath);
            imagecopyresampled($im, $logo, 60, 50, 0, 0, 90, 90, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }

        $white = $this->color($im, self::WHITE);
        $x     = 170;
        imagettftext($im, 13, 0, $x, 78, $white, $this->fontRegular, self::HEADER_LINES[0]);
        imagettftext($im, 17, 0, $x, 102, $white, $this->fontExtraBold, self::HEADER_LINES[1]);
        imagettftext($im, 14, 0, $x, 124, $white, $this->fontExtraBold, self::HEADER_LINES[2]);
    }

    private function drawFooter(\GdImage $im): void
    {
        $white = $this->color($im, self::WHITE);
        $this->drawCentered($im, $this->fontRegular, 13, self::FOOTER_LINE, self::WIDTH / 2, self::HEIGHT - 40, $white);
    }

    private function drawCentered(\GdImage $im, string $font, float $size, string $text, float $cx, float $y, int $color): void
    {
        $box   = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        imagettftext($im, $size, 0, (int) ($cx - $width / 2), (int) $y, $color, $font, $text);
    }

    private function drawCenteredBox(\GdImage $im, string $font, float $size, string $text, int $boxX, int $boxY, int $boxW, int $boxH, int $color): void
    {
        $box    = imagettfbbox($size, 0, $font, $text);
        $width  = $box[2] - $box[0];
        $height = $box[1] - $box[7];
        $x      = $boxX + (int) (($boxW - $width) / 2);
        $y      = $boxY + (int) (($boxH + $height) / 2);
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }

    // ── Text layout helpers ──────────────────────────────────────────────────

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

        return $lines;
    }

    /**
     * Pick the largest font size (down to $minSize) whose wrapped lines fit
     * within $maxHeight. Falls back to truncation with an ellipsis if even
     * the minimum size overflows — text never clips or overlaps other content.
     */
    private function fitText(string $font, string $text, float $maxWidth, float $maxHeight, float $maxSize, float $minSize = 11): array
    {
        for ($size = $maxSize; $size >= $minSize; $size -= 1) {
            $lines      = $this->wrapText($font, $size, $text, $maxWidth);
            $lineHeight = $size * 1.4;
            if (count($lines) * $lineHeight <= $maxHeight) {
                return [$size, $lineHeight, $lines];
            }
        }

        $lineHeight = $minSize * 1.4;
        $lines      = $this->wrapText($font, $minSize, $text, $maxWidth);
        $maxLines   = max(1, (int) floor($maxHeight / $lineHeight));
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1]) . '…';
        }

        return [$minSize, $lineHeight, $lines];
    }

    private function drawParagraph(\GdImage $im, string $font, float $size, float $lineHeight, array $lines, int $x, int $y, int $color): int
    {
        foreach ($lines as $line) {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $line);
            $y += (int) $lineHeight;
        }

        return $y;
    }

    /**
     * Draw a list of "Label: value" paragraphs, auto-shrinking the font so
     * the whole block fits within $maxHeight.
     */
    private function drawFittedParagraphList(\GdImage $im, string $font, array $paragraphs, int $x, int $y, int $maxWidth, int $maxHeight, float $maxSize, float $minSize, int $color): int
    {
        for ($size = $maxSize; $size >= $minSize; $size -= 1) {
            $lineHeight = $size * 1.4;
            $totalLines = 0;
            $wrappedAll = [];
            foreach ($paragraphs as $p) {
                $wrapped      = $this->wrapText($font, $size, $p, $maxWidth);
                $wrappedAll[] = $wrapped;
                $totalLines  += count($wrapped) + 0.4; // small gap between paragraphs
            }
            if ($totalLines * $lineHeight <= $maxHeight || $size <= $minSize) {
                foreach ($wrappedAll as $wrapped) {
                    $y = $this->drawParagraph($im, $font, $size, $lineHeight, $wrapped, $x, $y + $size, $color);
                    $y += (int) ($lineHeight * 0.4);
                }

                return $y;
            }
        }

        return $y;
    }

    private function drawFittedBulletOrParagraph(\GdImage $im, string $font, string $text, int $x, int $y, int $maxWidth, int $maxHeight, float $maxSize, float $minSize, int $color): int
    {
        $items = array_values(array_filter(preg_split('/\r?\n/', $text)));
        if (count($items) > 1) {
            return $this->drawFittedBulletList($im, $font, $items, $x, $y, $maxWidth, $maxHeight, $maxSize, $minSize, $color);
        }

        [$size, $lineHeight, $lines] = $this->fitText($font, $text, $maxWidth, $maxHeight, $maxSize, $minSize);

        return $this->drawParagraph($im, $font, $size, $lineHeight, $lines, $x, $y + $size, $color);
    }

    /**
     * Draw a bulleted list, auto-shrinking the font so it fits $maxHeight.
     * If even the minimum size overflows, trailing items are collapsed into
     * a "+N more" line rather than clipping or overlapping later content.
     */
    private function drawFittedBulletList(\GdImage $im, string $font, array $items, int $x, int $y, int $maxWidth, int $maxHeight, float $maxSize, float $minSize, int $color): int
    {
        if (empty($items)) {
            return $y;
        }

        for ($size = $maxSize; $size >= $minSize; $size -= 1) {
            $lineHeight = $size * 1.5;
            $wrappedAll = [];
            $totalLines = 0;
            foreach ($items as $item) {
                $wrapped      = $this->wrapText($font, $size, '•  ' . $item, $maxWidth);
                $wrappedAll[] = $wrapped;
                $totalLines  += count($wrapped);
            }
            if ($totalLines * $lineHeight <= $maxHeight) {
                foreach ($wrappedAll as $wrapped) {
                    $y = $this->drawParagraph($im, $font, $size, $lineHeight, $wrapped, $x, $y + $size, $color);
                }

                return $y;
            }
        }

        // Still doesn't fit at minSize — show as many as fit, then a summary line.
        $lineHeight = $minSize * 1.5;
        $maxLines   = max(1, (int) floor($maxHeight / $lineHeight) - 1);
        $shown      = 0;
        $linesUsed  = 0;
        foreach ($items as $item) {
            $wrapped = $this->wrapText($font, $minSize, '•  ' . $item, $maxWidth);
            if ($linesUsed + count($wrapped) > $maxLines) {
                break;
            }
            $y = $this->drawParagraph($im, $font, $minSize, $lineHeight, $wrapped, $x, $y + $minSize, $color);
            $linesUsed += count($wrapped);
            $shown++;
        }
        $remaining = count($items) - $shown;
        if ($remaining > 0) {
            imagettftext($im, $minSize, 0, $x, $y + $minSize, $color, $font, "+ {$remaining} more");
            $y += (int) $lineHeight;
        }

        return $y;
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
