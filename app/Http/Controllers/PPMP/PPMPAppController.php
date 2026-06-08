<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpCatalogue;
use App\Models\PPMP\PpmpItem;
use App\Models\PPMP\PpmpSetting;
use App\Services\PPMP\CostComputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PPMPAppController extends Controller
{
    public function __construct(private CostComputationService $costService)
    {
    }

    /**
     * View consolidated APP for a fiscal year.
     * Provides both:
     *   items       — individual PPMP items per unit (for review)
     *   consolidated — quantities aggregated by catalogue_id across all approved PPMPs
     */
    public function index(Request $request)
    {
        $this->authorize('consolidate', Ppmp::class);

        $fiscalYear = $request->input('fiscal_year', (int) date('Y'));

        // By-unit items (existing view)
        $items = PpmpItem::query()
            ->join('ppmp', 'ppmp_items.ppmp_id', '=', 'ppmp.id')
            ->join('divisions', 'ppmp.division_id', '=', 'divisions.id')
            ->where('ppmp.fiscal_year', $fiscalYear)
            ->whereIn('ppmp.status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
            ->select(
                'ppmp_items.*',
                'divisions.division_name',
                'divisions.acronym as division_acronym',
                'ppmp.ppmp_number'
            )
            ->orderByRaw("FIELD(ppmp_items.category, 'goods', 'infrastructure', 'consulting_services')")
            ->orderBy('ppmp_items.description')
            ->get();

        // Aggregated by catalogue_id — all months summed across approved PPMPs
        $consolidated = $this->buildConsolidated($fiscalYear);

        $totals = $this->costService->computeAPPTotals($fiscalYear);

        $approvedCount = Ppmp::forYear($fiscalYear)
            ->whereIn('status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
            ->count();

        return Inertia::render('PPMP/App/Index', [
            'items'         => $items,
            'consolidated'  => $consolidated,
            'totals'        => $totals,
            'fiscalYear'    => $fiscalYear,
            'approvedCount' => $approvedCount,
            'categories'    => PpmpItem::CATEGORY_LABELS,
            'methods'       => PpmpItem::PROCUREMENT_METHODS,
        ]);
    }

    /**
     * Consolidate all approved PPMPs into APP.
     */
    public function consolidate(Request $request)
    {
        $this->authorize('consolidate', Ppmp::class);

        $data = $request->validate([
            'fiscal_year' => 'required|integer',
        ]);

        $ppmps = Ppmp::forYear($data['fiscal_year'])
            ->byStatus(Ppmp::STATUS_APPROVED)
            ->get();

        if ($ppmps->isEmpty()) {
            return back()->with('error', 'No approved PPMPs to consolidate.');
        }

        DB::transaction(function () use ($ppmps) {
            $user = auth()->user();
            foreach ($ppmps as $ppmp) {
                $ppmp->consolidate($user);
            }
        });

        return back()->with('success', $ppmps->count() . ' PPMP(s) consolidated into APP.');
    }

    /**
     * Export the consolidated APP-CSE as Excel (mPhilGEPS submission format).
     * Quantities are aggregated from all approved/consolidated PPMPs for the fiscal year.
     */
    public function excel(Request $request)
    {
        $this->authorize('consolidate', Ppmp::class);

        $fiscalYear = (int) $request->input('fiscal_year', (int) date('Y'));
        $agencyName = PpmpSetting::getValue('agency_name', 'Philippine Science High School - Caraga Region Campus (PSHS-CRC)');

        $consolidated = $this->buildConsolidated($fiscalYear);

        $spreadsheet = new Spreadsheet();
        $ws          = $spreadsheet->getActiveSheet();
        $ws->setTitle("APP-CSE {$fiscalYear} FORM");

        // Column widths
        $colWidths = [5, 18, 40, 8, 5, 5, 5, 8, 14, 5, 5, 5, 8, 14, 5, 5, 5, 8, 14, 5, 5, 5, 8, 14, 10, 14, 16];
        foreach ($colWidths as $i => $w) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }

        // Header block
        $row = 1;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", 'ANNUAL PROCUREMENT PLAN FOR COMMON-USE SUPPLIES AND EQUIPMENT (APP-CSE)');
        $this->hdrStyle($ws, "A{$row}:AA{$row}", 14, true, 'FFFFFF', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", "Fiscal Year: {$fiscalYear}");
        $this->hdrStyle($ws, "A{$row}:AA{$row}", 11, false, 'FFFFFF', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", "Agency/Office: {$agencyName}");
        $this->hdrStyle($ws, "A{$row}:AA{$row}", 10, false, 'D9E1F2', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", 'CONSOLIDATED ANNUAL PROCUREMENT PLAN — All Divisions/Units');
        $this->hdrStyle($ws, "A{$row}:AA{$row}", 9, false, 'EEF2F7', '1F3864');

        $row++;
        $ws->mergeCells("A{$row}:AA{$row}");
        $ws->setCellValue("A{$row}", '');
        $ws->getRowDimension($row)->setRowHeight(4);

        // Column headers
        $row++;
        $h1 = $row;
        $ws->mergeCells("E{$h1}:G{$h1}");
        $ws->mergeCells("J{$h1}:L{$h1}");
        $ws->mergeCells("O{$h1}:Q{$h1}");
        $ws->mergeCells("T{$h1}:V{$h1}");
        foreach (['A'=>'No.','B'=>'Stock No.','C'=>'Description','D'=>"Unit of\nMeasure",'E'=>'FIRST QUARTER','H'=>"Q1\nTotal",'I'=>"Q1\nAmt (₱)",'J'=>'SECOND QUARTER','M'=>"Q2\nTotal",'N'=>"Q2\nAmt (₱)",'O'=>'THIRD QUARTER','R'=>"Q3\nTotal",'S'=>"Q3\nAmt (₱)",'T'=>'FOURTH QUARTER','W'=>"Q4\nTotal",'X'=>"Q4\nAmt (₱)",'Y'=>"TOTAL\nQTY",'Z'=>"UNIT\nCOST (₱)",'AA'=>"TOTAL\nAMT (₱)"] as $col => $val) {
            $ws->setCellValue("{$col}{$h1}", $val);
        }
        $ws->getStyle("A{$h1}:AA{$h1}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1F3864']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BDD7EE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9DC3E6']]],
        ]);

        $row++;
        $h2 = $row;
        foreach (['A','B','C','D','H','I','M','N','R','S','W','X','Y','Z','AA'] as $col) {
            $ws->mergeCells("{$col}{$h1}:{$col}{$h2}");
        }
        foreach (['E'=>'Jan','F'=>'Feb','G'=>'Mar','J'=>'Apr','K'=>'May','L'=>'Jun','O'=>'Jul','P'=>'Aug','Q'=>'Sep','T'=>'Oct','U'=>'Nov','V'=>'Dec'] as $col => $val) {
            $ws->setCellValue("{$col}{$h2}", $val);
        }
        $ws->getStyle("A{$h2}:AA{$h2}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1F3864']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEEFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9DC3E6']]],
        ]);
        $ws->getRowDimension($h1)->setRowHeight(22);
        $ws->getRowDimension($h2)->setRowHeight(14);

        // Data rows
        $row++;
        $seq = 1;
        $currentPart  = 1;
        $currentGroup = null;
        $part1Total   = 0.0;
        $part2Total   = 0.0;

        foreach ($consolidated as $c) {
            if ($c['part'] === 2 && $currentPart === 1) {
                // Part I summary
                $row = $this->writeSummary($ws, $row, $part1Total);

                // Part II header
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
            if ($c['category_group'] !== null && $c['category_group'] !== $currentGroup) {
                $currentGroup = $c['category_group'];
                $ws->mergeCells("A{$row}:AA{$row}");
                $ws->setCellValue("A{$row}", strtoupper($currentGroup));
                $fill = $currentPart === 1 ? 'D9E1F2' : 'E2EFDA';
                $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1F3864']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9DC3E6']]],
                ]);
                $ws->getRowDimension($row)->setRowHeight(14);
                $row++;
            }

            $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            $mq = array_map(fn ($m) => (int) ($c[$m] ?? 0), $months);
            [$q1,$q2,$q3,$q4] = [$mq[0]+$mq[1]+$mq[2], $mq[3]+$mq[4]+$mq[5], $mq[6]+$mq[7]+$mq[8], $mq[9]+$mq[10]+$mq[11]];
            $totalQty = $q1 + $q2 + $q3 + $q4;
            $unitPrice = (float) $c['unit_price'];
            $totalAmt  = round($totalQty * $unitPrice, 2);

            $ws->fromArray([
                $seq++,
                $c['stock_number'],
                $c['description'],
                $c['unit'],
                $mq[0],$mq[1],$mq[2],$q1,round($q1*$unitPrice,2),
                $mq[3],$mq[4],$mq[5],$q2,round($q2*$unitPrice,2),
                $mq[6],$mq[7],$mq[8],$q3,round($q3*$unitPrice,2),
                $mq[9],$mq[10],$mq[11],$q4,round($q4*$unitPrice,2),
                $totalQty,$unitPrice,$totalAmt,
            ], null, "A{$row}");

            $highlight = $totalQty > 0 ? ($currentPart === 1 ? 'EBF3FA' : 'F0FFF0') : null;
            $this->dataRowStyle($ws, $row, $highlight);
            $row++;

            if ($currentPart === 1) {
                $part1Total += $totalAmt;
            } else {
                $part2Total += $totalAmt;
            }
        }

        // Part II summary
        $row = $this->writeSummary($ws, $row, $part2Total);

        // Grand total
        $grand = $part1Total + $part2Total;
        $row++;
        $ws->mergeCells("A{$row}:Y{$row}");
        $ws->setCellValue("A{$row}", 'GRAND TOTAL (Part I + Part II)');
        $ws->setCellValue("AA{$row}", $grand);
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F3864']]],
        ]);
        $ws->getStyle("AA{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getRowDimension($row)->setRowHeight(16);

        // Signature block
        $row += 2;
        foreach (['A' => 'Prepared by:', 'J' => 'Reviewed/Certified by:', 'S' => 'Approved by:'] as $col => $lbl) {
            $end = ($col === 'A') ? 'I' : ($col === 'J' ? 'R' : 'AA');
            $ws->mergeCells("{$col}{$row}:{$end}{$row}");
            $ws->setCellValue("{$col}{$row}", $lbl);
        }
        $row++;
        foreach (['A' => ['', 'I'], 'J' => ['', 'R'], 'S' => ['', 'AA']] as $col => [$name, $end]) {
            $ws->mergeCells("{$col}{$row}:{$end}{$row}");
            $ws->getStyle("{$col}{$row}:{$end}{$row}")->applyFromArray([
                'font'    => ['bold' => true, 'size' => 9],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM]],
            ]);
        }
        $row++;
        $ws->mergeCells("A{$row}:I{$row}"); $ws->setCellValue("A{$row}", 'Property/Supply Officer');
        $ws->mergeCells("J{$row}:R{$row}"); $ws->setCellValue("J{$row}", 'BAC Chairperson / Accountant / Budget Officer');
        $ws->mergeCells("S{$row}:AA{$row}"); $ws->setCellValue("S{$row}", 'Head of Office');

        // Output
        $filename = "APP-CSE_Consolidated_{$fiscalYear}.xlsx";
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

    /**
     * Build consolidated data: all active catalogue items with quantities summed
     * from all approved/consolidated PPMPs for the given fiscal year.
     */
    private function buildConsolidated(int $fiscalYear): array
    {
        $monthCols = implode(', ', array_map(fn ($m) => "COALESCE(SUM(pi.{$m}), 0) as {$m}", ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']));

        $rows = DB::table('ppmp_catalogue as c')
            ->leftJoin('ppmp_items as pi', function ($join) use ($fiscalYear) {
                $join->on('pi.catalogue_id', '=', 'c.id')
                    ->whereIn('pi.ppmp_id', function ($sub) use ($fiscalYear) {
                        $sub->select('id')
                            ->from('ppmp')
                            ->where('fiscal_year', $fiscalYear)
                            ->whereIn('status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED]);
                    });
            })
            ->where('c.fiscal_year', $fiscalYear)
            ->where('c.is_active', true)
            ->selectRaw("c.id, c.stock_number, c.description, c.unit, c.unit_cost, c.part, c.category_group, c.row_position, {$monthCols}, COALESCE(SUM(pi.total_quantity), 0) as total_quantity, AVG(CASE WHEN pi.office_unit_cost > 0 THEN pi.office_unit_cost END) as avg_office_unit_cost")
            ->groupBy('c.id','c.stock_number','c.description','c.unit','c.unit_cost','c.part','c.category_group','c.row_position')
            ->orderBy('c.part')
            ->orderBy('c.row_position')
            ->get();

        return $rows->map(function ($r) {
            $r = (array) $r;
            // Unit price: Part I uses catalogue price; Part II uses average canvassed price (or 0 if none)
            $r['unit_price'] = $r['part'] === 1
                ? (float) $r['unit_cost']
                : (float) ($r['avg_office_unit_cost'] ?? 0);
            return $r;
        })->all();
    }

    private function writeSummary(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row, float $subtotal): int
    {
        $inflation = round($subtotal * 0.10, 2);
        $grand     = $subtotal + $inflation;

        foreach ([
            ['A. Total Amount', $subtotal],
            ['B. 10% Inflation Provision', $inflation],
            ['C. Freight/Handling/Insurance', 0.00],
            ['D. Grand Total', $grand],
            ['E. Approved Budget for Contract (ABC)', 0.00],
        ] as [$label, $amount]) {
            $ws->mergeCells("A{$row}:Y{$row}");
            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("AA{$row}", $amount);
            $ws->getStyle("A{$row}:AA{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 8, 'italic' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
            ]);
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $ws->getStyle("AA{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $ws->getRowDimension($row)->setRowHeight(13);
            $row++;
        }

        return $row;
    }

    private function hdrStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $range, int $size, bool $bold, string $bg, string $fg): void
    {
        $ws->getStyle($range)->applyFromArray([
            'font'      => ['bold' => $bold, 'size' => $size, 'color' => ['rgb' => $fg]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        preg_match('/(\d+)$/', $range, $m);
        if ($m) {
            $ws->getRowDimension((int) $m[1])->setRowHeight(18);
        }
    }

    private function dataRowStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row, ?string $bgRgb = null): void
    {
        $style = [
            'font'      => ['size' => 8],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CCCCCC']]],
        ];
        if ($bgRgb) {
            $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRgb]];
        }
        $ws->getStyle("A{$row}:AA{$row}")->applyFromArray($style);

        foreach (range(5, 27) as $col) {
            $ws->getStyleByColumnAndRow($col, $row)->getNumberFormat()->setFormatCode('#,##0.##');
            $ws->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        foreach ([9, 14, 19, 24, 26, 27] as $col) {
            $ws->getStyleByColumnAndRow($col, $row)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach ([5,6,7,8,10,11,12,13,15,16,17,18,20,21,22,23,25] as $col) {
            $ws->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $ws->getRowDimension($row)->setRowHeight(13);
    }
}
