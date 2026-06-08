<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use App\Models\PPMP\PpmpSetting;
use App\Services\PPMP\CostComputationService;
use Mpdf\Mpdf;

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
}
