<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpCatalogue;
use App\Models\PPMP\PpmpSetting;
use App\Services\PPMP\CostComputationService;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PPMPExportController extends Controller
{
    public function __construct(private CostComputationService $costService)
    {
    }

    /**
     * Export PPMP as PDF.
     */
    public function pdf(Ppmp $ppmp)
    {
        $this->authorize('export', $ppmp);

        $ppmp->load(['division', 'preparer', 'approver', 'parentPpmp:id,ppmp_number']);
        $items = $ppmp->items()->get();
        $summary = $this->costService->computePPMPSummary($ppmp->id);
        $grandTotal = $ppmp->grandTotal();
        $agencyName = PpmpSetting::getValue('agency_name', 'Philippine Science High School - Caraga Region Campus');

        $html = view('exports.ppmp', compact('ppmp', 'items', 'summary', 'grandTotal', 'agencyName'))->render();

        $mpdf = new Mpdf([
            'orientation' => 'L',
            'format'      => 'Legal',
            'margin_left'   => 8,
            'margin_right'  => 8,
            'margin_top'    => 8,
            'margin_bottom' => 8,
            'default_font'  => 'arial',
        ]);

        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$ppmp->ppmp_number}.pdf\"",
        ]);
    }

    /**
     * Export PPMP as APP-CSE Excel (XLSX) — the exact mPhilGEPS submission format.
     *
     * Column layout (A–AA, index 0–26):
     *   A=Seq, B=Stock#, C=Description, D=Unit,
     *   E=Jan, F=Feb, G=Mar, H=Q1, I=Q1Amt,
     *   J=Apr, K=May, L=Jun, M=Q2, N=Q2Amt,
     *   O=Jul, P=Aug, Q=Sep, R=Q3, S=Q3Amt,
     *   T=Oct, U=Nov, V=Dec, W=Q4, X=Q4Amt,
     *   Y=TotalQty, Z=UnitPrice, AA=TotalAmt
     *
     * All active catalogue items appear in row_position order (0s for items not in this PPMP).
     * Manual Part II items appended after catalogue items.
     */
    public function excel(Ppmp $ppmp)
    {
        $this->authorize('export', $ppmp);

        $ppmp->load(['division:id,division_name,acronym', 'preparer:id,name,position', 'approver:id,name,position']);
        $fiscalYear = $ppmp->fiscal_year;
        $agencyName = PpmpSetting::getValue('agency_name', 'Philippine Science High School - Caraga Region Campus (PSHS-CRC)');

        // All active catalogue items for this FY, in correct output order
        $catalogue = PpmpCatalogue::forYear($fiscalYear)
            ->orderBy('part')
            ->orderBy('row_position')
            ->get();

        // Index PPMP items by catalogue_id for O(1) lookup
        $ppmpByCat  = $ppmp->items()->whereNotNull('catalogue_id')->get()->keyBy('catalogue_id');
        $manualItems = $ppmp->items()->whereNull('catalogue_id')->orderBy('sort_order')->get();

        // ── Build spreadsheet ─────────────────────────────────────────────────

        $spreadsheet = new Spreadsheet();
        $ws          = $spreadsheet->getActiveSheet();
        $ws->setTitle("APP-CSE {$fiscalYear} FORM");

        // Column widths  A  B   C    D   E-G H   I  J-L M  N  O-Q R  S  T-V W  X  Y  Z  AA
        $colWidths = [5, 18, 40, 8, 5, 5, 5, 8, 14, 5, 5, 5, 8, 14, 5, 5, 5, 8, 14, 5, 5, 5, 8, 14, 10, 14, 16];
        foreach ($colWidths as $i => $w) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }

        // ── Agency header block (rows 1-8) ────────────────────────────────────

        $row = 1;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", 'ANNUAL PROCUREMENT PLAN FOR COMMON-USE SUPPLIES AND EQUIPMENT (APP-CSE)');
        $this->styleHeader($ws, "A{$row}:AA{$row}", 14, true, 'FFFFFF', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", "Fiscal Year: {$fiscalYear}");
        $this->styleHeader($ws, "A{$row}:AA{$row}", 11, false, 'FFFFFF', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", "Agency/Office: {$agencyName}");
        $this->styleHeader($ws, "A{$row}:AA{$row}", 10, false, 'D9E1F2', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", "Division/Unit: " . ($ppmp->division->division_name ?? '') . ($ppmp->division->acronym ? " ({$ppmp->division->acronym})" : ''));
        $this->styleHeader($ws, "A{$row}:AA{$row}", 10, false, 'D9E1F2', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", "PPMP No.: {$ppmp->ppmp_number}  |  Status: " . strtoupper($ppmp->status));
        $this->styleHeader($ws, "A{$row}:AA{$row}", 9, false, 'EEF2F7', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", '');
        $ws->getRowDimension($row)->setRowHeight(4);

        // ── Column headers (rows 7-8) ─────────────────────────────────────────

        $row++;
        $headerRow1 = $row;
        $headers1 = [
            'A' => 'No.',
            'B' => 'Stock No.',
            'C' => 'Description',
            'D' => 'Unit of\nMeasure',
            'E' => 'FIRST QUARTER',
            'H' => 'Q1\nTotal',
            'I' => 'Q1\nAmt (₱)',
            'J' => 'SECOND QUARTER',
            'M' => 'Q2\nTotal',
            'N' => 'Q2\nAmt (₱)',
            'O' => 'THIRD QUARTER',
            'R' => 'Q3\nTotal',
            'S' => 'Q3\nAmt (₱)',
            'T' => 'FOURTH QUARTER',
            'W' => 'Q4\nTotal',
            'X' => 'Q4\nAmt (₱)',
            'Y' => 'TOTAL\nQTY',
            'Z' => 'UNIT\nCOST (₱)',
            'AA' => 'TOTAL\nAMT (₱)',
        ];

        $ws->mergeCells("E{$headerRow1}:G{$headerRow1}");
        $ws->mergeCells("J{$headerRow1}:L{$headerRow1}");
        $ws->mergeCells("O{$headerRow1}:Q{$headerRow1}");
        $ws->mergeCells("T{$headerRow1}:V{$headerRow1}");

        foreach ($headers1 as $col => $val) {
            $ws->setCellValue("{$col}{$headerRow1}", $val);
        }
        $ws->getStyle("A{$headerRow1}:AA{$headerRow1}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1F3864']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BDD7EE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9DC3E6']]],
        ]);

        $row++;
        $headerRow2 = $row;
        $headers2 = ['E' => 'Jan', 'F' => 'Feb', 'G' => 'Mar', 'J' => 'Apr', 'K' => 'May', 'L' => 'Jun',
                     'O' => 'Jul', 'P' => 'Aug', 'Q' => 'Sep', 'T' => 'Oct', 'U' => 'Nov', 'V' => 'Dec'];

        foreach (['A','B','C','D','H','I','M','N','R','S','W','X','Y','Z','AA'] as $col) {
            $ws->mergeCells("{$col}{$headerRow1}:{$col}{$headerRow2}");
        }
        foreach ($headers2 as $col => $val) {
            $ws->setCellValue("{$col}{$headerRow2}", $val);
        }
        $ws->getStyle("A{$headerRow2}:AA{$headerRow2}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1F3864']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEEFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9DC3E6']]],
        ]);

        $ws->getRowDimension($headerRow1)->setRowHeight(22);
        $ws->getRowDimension($headerRow2)->setRowHeight(14);

        // ── Data rows ─────────────────────────────────────────────────────────

        $row++;
        $dataStartRow  = $row;
        $seq           = 1;
        $currentPart   = 1;
        $currentGroup  = null;
        $part1Rows     = [];
        $part2Rows     = [];
        $part1Total    = 0.0;
        $part2Total    = 0.0;

        foreach ($catalogue as $cat) {
            // Part II boundary
            if ($cat->part === 2 && $currentPart === 1) {
                // Part I summary rows
                $row = $this->writeSummaryRows($ws, $row, $part1Total, $fiscalYear, 1);

                // Part II section header
                $row++;
                $ws->mergeCells("A{$row}:AA{$row}");
                $ws->setCellValue("A{$row}", 'PART II — Non-PS-DBM Items (Price to be filled by the office)');
                $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '375623']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6F9B47']]],
                ]);
                $ws->getRowDimension($row)->setRowHeight(16);
                $row++;

                $currentPart  = 2;
                $currentGroup = null;
                $seq          = 1;
            }

            // Category group header
            if ($cat->category_group !== null && $cat->category_group !== $currentGroup) {
                $currentGroup = $cat->category_group;
                $ws->mergeCells("A{$row}:AA{$row}");
                $ws->setCellValue("A{$row}", strtoupper($currentGroup));
                $fillColor = $currentPart === 1 ? 'D9E1F2' : 'E2EFDA';
                $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1F3864']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9DC3E6']]],
                ]);
                $ws->getRowDimension($row)->setRowHeight(14);
                $row++;
            }

            // Item row
            $item = $ppmpByCat[$cat->id] ?? null;
            [$rowTotal, $row] = $this->writeItemRow(
                $ws, $row, $seq++, $cat, $item, $currentPart
            );

            if ($currentPart === 1) {
                $part1Total += $rowTotal;
                $part1Rows[] = $row - 1;
            } else {
                $part2Total += $rowTotal;
                $part2Rows[] = $row - 1;
            }
        }

        // Part II summary (from catalogue items)
        $row = $this->writeSummaryRows($ws, $row, $part2Total, $fiscalYear, 2);

        // Manual Part II items
        if ($manualItems->isNotEmpty()) {
            $row++;
            $ws->mergeCells("A{$row}:AA{$row}");
            $ws->setCellValue("A{$row}", 'OTHER ITEMS (Not in APP-CSE Pre-defined List)');
            $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1F3864']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFD700']]],
            ]);
            $ws->getRowDimension($row)->setRowHeight(14);
            $row++;

            $manualTotal = 0.0;
            foreach ($manualItems as $item) {
                $unitPrice = (float) $item->unit_cost;
                $qty = (int) $item->total_quantity;
                $total = round($qty * $unitPrice, 2);
                $manualTotal += $total;

                $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
                $mq = array_map(fn ($m) => (int) ($item->$m ?? 0), $months);
                [$q1,$q2,$q3,$q4] = [$mq[0]+$mq[1]+$mq[2], $mq[3]+$mq[4]+$mq[5], $mq[6]+$mq[7]+$mq[8], $mq[9]+$mq[10]+$mq[11]];

                $ws->fromArray([
                    $seq++,
                    $item->code,
                    $item->description,
                    $item->unit,
                    $mq[0], $mq[1], $mq[2], $q1, round($q1 * $unitPrice, 2),
                    $mq[3], $mq[4], $mq[5], $q2, round($q2 * $unitPrice, 2),
                    $mq[6], $mq[7], $mq[8], $q3, round($q3 * $unitPrice, 2),
                    $mq[9], $mq[10], $mq[11], $q4, round($q4 * $unitPrice, 2),
                    $qty, $unitPrice, $total,
                ], null, "A{$row}");

                $this->styleDataRow($ws, $row, false, 'FFF2CC');
                $row++;
            }

            // Manual items subtotal
            $ws->mergeCells("A{$row}:Y{$row}");
            $ws->setCellValue("A{$row}", 'Other Items Subtotal');
            $ws->setCellValue("AA{$row}", $manualTotal);
            $this->styleSubtotalRow($ws, $row);
            $row++;
        }

        // ── Grand Total row ───────────────────────────────────────────────────

        $grandTotal = $part1Total + $part2Total + ($manualItems->isNotEmpty() ? $manualItems->sum(fn ($i) => (float) $i->total_cost) : 0);
        $row++;
        $ws->mergeCells("A{$row}:Y{$row}");
        $ws->setCellValue("A{$row}", 'GRAND TOTAL (Part I + Part II)');
        $ws->setCellValue("AA{$row}", $grandTotal);
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F3864']]],
        ]);
        $ws->getStyle("AA{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getRowDimension($row)->setRowHeight(16);

        // ── Signature block ───────────────────────────────────────────────────

        $row += 2;
        $ws->mergeCells("A{$row}:I{$row}");
        $ws->mergeCells("J{$row}:R{$row}");
        $ws->mergeCells("S{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", 'Prepared by:');
        $ws->setCellValue("J{$row}", 'Reviewed/Certified by:');
        $ws->setCellValue("S{$row}", 'Approved by:');
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'font'      => ['size' => 8, 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $row++;
        $ws->mergeCells("A{$row}:I{$row}");
        $ws->mergeCells("J{$row}:R{$row}");
        $ws->mergeCells("S{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", strtoupper($ppmp->preparer?->name ?? ''));
        $ws->setCellValue("J{$row}", '');
        $ws->setCellValue("S{$row}", '');
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);

        $row++;
        $ws->mergeCells("A{$row}:I{$row}");
        $ws->mergeCells("J{$row}:R{$row}");
        $ws->mergeCells("S{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", 'Property/Supply Officer');
        $ws->setCellValue("J{$row}", 'BAC Chairperson / Accountant / Budget Officer');
        $ws->setCellValue("S{$row}", 'Head of Office');
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'font'      => ['size' => 8],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $row++;
        $ws->mergeCells("A{$row}:I{$row}");
        $ws->setCellValue("A{$row}", 'Date: ___________________');
        $ws->mergeCells("J{$row}:R{$row}");
        $ws->setCellValue("J{$row}", 'Date: ___________________');
        $ws->mergeCells("S{$row}:AA{$row}");
        $ws->setCellValue("S{$row}", 'Date: ___________________');
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'font'      => ['size' => 8],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // ── Freeze panes at data start, set print options ─────────────────────

        $ws->freezePane("A{$dataStartRow}");
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $ws->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
        $ws->getPageSetup()->setFitToPage(true);
        $ws->getPageSetup()->setFitToWidth(1);
        $ws->getPageSetup()->setFitToHeight(0);
        $ws->getHeaderFooter()->setOddHeader("&C&\"Arial,Bold\"APP-CSE FY{$fiscalYear} — {$ppmp->ppmp_number}");
        $ws->getHeaderFooter()->setOddFooter('&C Page &P of &N');

        // ── Output ────────────────────────────────────────────────────────────

        $filename = "APP-CSE_{$fiscalYear}_{$ppmp->ppmp_number}.xlsx";
        $writer   = IOFactory::createWriter($spreadsheet, 'Xlsx');

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function writeItemRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row, int $seq, PpmpCatalogue $cat, $item, int $part): array
    {
        $months    = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
        $mq        = array_map(fn ($m) => (int) ($item?->$m ?? 0), $months);
        [$q1,$q2,$q3,$q4] = [
            $mq[0]+$mq[1]+$mq[2],
            $mq[3]+$mq[4]+$mq[5],
            $mq[6]+$mq[7]+$mq[8],
            $mq[9]+$mq[10]+$mq[11],
        ];
        $totalQty = $q1 + $q2 + $q3 + $q4;

        // Part I: fixed PS-DBM price; Part II: office-supplied price
        $unitPrice = $part === 1
            ? (float) $cat->unit_cost
            : (float) ($item?->office_unit_cost ?? 0);

        $totalAmt  = round($totalQty * $unitPrice, 2);

        $ws->fromArray([
            $seq,
            $cat->stock_number,
            $cat->description,
            $cat->unit,
            $mq[0], $mq[1], $mq[2], $q1,  round($q1  * $unitPrice, 2),
            $mq[3], $mq[4], $mq[5], $q2,  round($q2  * $unitPrice, 2),
            $mq[6], $mq[7], $mq[8], $q3,  round($q3  * $unitPrice, 2),
            $mq[9], $mq[10],$mq[11],$q4,  round($q4  * $unitPrice, 2),
            $totalQty, $unitPrice, $totalAmt,
        ], null, "A{$row}");

        $highlight = ($totalQty > 0) ? ($part === 1 ? 'EBF3FA' : 'F0FFF0') : null;
        $this->styleDataRow($ws, $row, false, $highlight);
        $row++;

        return [$totalAmt, $row];
    }

    private function writeSummaryRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row, float $subtotal, int $fiscalYear, int $part): int
    {
        $inflation  = round($subtotal * 0.10, 2);
        $grandTotal = $subtotal + $inflation;
        $partLabel  = $part === 1 ? 'Part I' : 'Part II';

        $summaryRows = [
            ['A. Total Amount', $subtotal],
            ['B. 10% Inflation Provision', $inflation],
            ['C. Freight/Handling/Insurance', 0.00],
            ['D. Grand Total (' . $partLabel . ')', $grandTotal],
            ['E. Approved Budget for Contract (ABC)', 0.00],
        ];

        foreach ($summaryRows as [$label, $amount]) {
            $ws->mergeCells("A{$row}:Y{$row}");
            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("AA{$row}", $amount);
            $this->styleSubtotalRow($ws, $row);
            $row++;
        }

        return $row;
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $range, int $fontSize, bool $bold, string $bgRgb, string $fontRgb): void
    {
        $ws->getStyle($range)->applyFromArray([
            'font'      => ['bold' => $bold, 'size' => $fontSize, 'color' => ['rgb' => $fontRgb]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRgb]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        preg_match('/(\d+)$/', $range, $m);
        if ($m) {
            $ws->getRowDimension((int) $m[1])->setRowHeight(18);
        }
    }

    private function styleDataRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row, bool $isHeader = false, ?string $bgRgb = null): void
    {
        $range = "A{$row}:AA{$row}";
        $style = [
            'font'      => ['size' => 8],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CCCCCC']]],
        ];

        if ($bgRgb) {
            $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRgb]];
        }

        $ws->getStyle($range)->applyFromArray($style);

        // Numeric columns: E-I, J-N, O-S, T-X, Y-Z, AA
        $numericCols = array_merge(range(5, 27)); // columns 5-27 = E-AA
        foreach ($numericCols as $col) {
            $ws->getStyleByColumnAndRow($col, $row)->getNumberFormat()->setFormatCode('#,##0.##');
        }
        // Amount columns show 2dp
        foreach ([9, 14, 19, 24, 26, 27] as $col) {
            $ws->getStyleByColumnAndRow($col, $row)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Right-align numeric columns
        foreach ($numericCols as $col) {
            $ws->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Center align months, quarter totals
        foreach ([5,6,7,8,10,11,12,13,15,16,17,18,20,21,22,23,25] as $col) {
            $ws->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $ws->getRowDimension($row)->setRowHeight(13);
    }

    private function styleSubtotalRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row): void
    {
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'italic' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);
        $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("AA{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getRowDimension($row)->setRowHeight(13);
    }
}
