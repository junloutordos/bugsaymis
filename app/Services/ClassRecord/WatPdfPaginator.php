<?php

namespace App\Services\ClassRecord;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;

/**
 * Decides where the WAT PDF's per-day rowspan groups should split across
 * pages. mPDF cannot split a rowspan cell across a page break — it always
 * keeps the whole spanned block atomic — so a busy day pushed to the next
 * page would otherwise leave a blank gap at the bottom of the previous one.
 *
 * Rather than re-implementing mPDF's font/line-wrap layout math to predict
 * row heights ourselves, this renders the real heading/thead/row markup
 * (same partials as wat-pdf.blade.php, so there's no drift risk) through a
 * throwaway mPDF instance as a sequence of self-closed single-row tables —
 * mPDF lays out and positions a closed table immediately (unlike one long
 * table, which it buffers whole and only paginates once closed), so
 * $mpdf->page after each write tells us exactly which page that row lands
 * on. Consecutive same-day rows sharing a page become one rowspan "chunk";
 * a day that crosses a page boundary becomes multiple chunks, with
 * continuation chunks labelled "(cont'd)".
 *
 * Because each measurement row is its own table rather than a row inside
 * one continuous table, adjacent borders don't collapse the way they would
 * in the real render — this makes the measurement slightly (~1 border
 * width per row) TALLER than reality. That bias only ever makes a chunk
 * boundary land a little earlier than strictly necessary (worst case: a
 * few mm of unused space at a page break); it can never cause the real
 * render to overflow past where we measured, so it can't reintroduce the
 * original gap bug.
 */
class WatPdfPaginator
{
    /**
     * @param  array{level: int|string, name: string}  $section
     * @param  array{week_start: string, week_end: string, days: array<int, array{date: string, items: Collection}>}  $wat
     * @param  array{name?: string}|null  $schoolYear
     * @param  array{format: string, orientation: string, margin_top: float, margin_bottom: float, margin_left: float, margin_right: float, tempDir: string}  $pageConfig
     * @return array<int, array{date: string, chunks: array<int, array{label: string, items: Collection}>}>
     */
    public function chunk(array $section, array $wat, ?array $schoolYear, array $pageConfig): array
    {
        $days = $wat['days'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $pageConfig['format'],
            'orientation' => $pageConfig['orientation'],
            'margin_left' => $pageConfig['margin_left'],
            'margin_right' => $pageConfig['margin_right'],
            'margin_top' => $pageConfig['margin_top'],
            'margin_bottom' => $pageConfig['margin_bottom'],
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $pageConfig['tempDir'],
        ]);

        $mpdf->WriteHTML('<style>'.view('class-record.partials.wat-pdf-styles')->render().'</style>');
        $mpdf->WriteHTML(view('class-record.partials.wat-pdf-heading', compact('section', 'wat', 'schoolYear'))->render());
        $mpdf->WriteHTML('<table class="wat-table" style="margin:0">'.view('class-record.partials.wat-pdf-thead-row')->render().'</table>');

        $pages = [];

        // Each row is measured as its OWN closed <table class="wat-table">
        // so mPDF lays it out (and updates $mpdf->page) immediately — see
        // class docblock. `.wat-table` also carries `margin-bottom: 4mm`,
        // meant for the ONE real table's spacing before the signatories
        // block below it; left in place here, that 4mm would be added
        // after every single measured row, wildly overestimating row
        // height and making the paginator split days far earlier than
        // necessary. The inline `style="margin:0"` cancels it for these
        // per-row measurement tables only.
        foreach ($days as $dayIdx => $day) {
            if ($day['items']->isEmpty()) {
                $mpdf->WriteHTML(
                    '<table class="wat-table" style="margin:0"><tr><td width="10%" class="wat-day">&nbsp;</td>'.
                    '<td colspan="7" class="wat-empty">No assessments scheduled</td></tr></table>'
                );
                $pages[$dayIdx][] = $mpdf->page;

                continue;
            }

            foreach ($day['items'] as $itemIdx => $item) {
                $mpdf->WriteHTML(
                    '<table class="wat-table" style="margin:0"><tr><td width="10%" class="wat-day">&nbsp;</td>'.
                    view('class-record.partials.wat-pdf-item-cells', compact('item'))->render().
                    '</tr></table>'
                );
                $pages[$dayIdx][$itemIdx] = $mpdf->page;
            }
        }

        return collect($days)->map(function ($day, $dayIdx) use ($pages) {
            $label = Carbon::parse($day['date'])->format('l, M j');

            if ($day['items']->isEmpty()) {
                return [
                    'date' => $day['date'],
                    'chunks' => [['label' => $label, 'items' => $day['items']]],
                ];
            }

            $chunks = [];
            $currentPage = null;
            $currentItems = [];

            foreach ($day['items'] as $itemIdx => $item) {
                $itemPage = $pages[$dayIdx][$itemIdx];

                if ($currentPage !== null && $itemPage !== $currentPage) {
                    $chunks[] = collect($currentItems)->values();
                    $currentItems = [];
                }

                $currentItems[] = $item;
                $currentPage = $itemPage;
            }

            if ($currentItems !== []) {
                $chunks[] = collect($currentItems)->values();
            }

            return [
                'date' => $day['date'],
                'chunks' => collect($chunks)->map(fn ($items, $chunkIdx) => [
                    'label' => $chunkIdx === 0 ? $label : $label." (cont'd)",
                    'items' => $items,
                ])->all(),
            ];
        })->values()->all();
    }
}
