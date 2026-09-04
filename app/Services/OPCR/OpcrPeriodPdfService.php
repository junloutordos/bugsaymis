<?php

namespace App\Services\OPCR;

use App\Models\OPCR\OpcrPeriod;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpcrPeriodPdfService
{
    public function stream(OpcrPeriod $period): StreamedResponse
    {
        $period->loadMissing([
            'indicators.subStrategy.strategy.pillar',
            'indicators.agencyOutcome',
            'indicators.divisions',
            'indicators.actuals',
        ]);

        $html = view('opcr.pdf', [
            'period' => $period,
            'indicators' => $period->indicators,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('OPCR FY ' . $period->fiscal_year);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'OPCR_FY' . $period->fiscal_year . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => strlen($pdfBytes),
        ]);
    }
}
