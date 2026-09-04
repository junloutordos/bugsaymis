<?php

namespace App\Services\OPCR;

use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrSetting;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpcrPdfService
{
    public function stream(int $fiscalYear): StreamedResponse
    {
        $indicators = OpcrIndicator::forFiscalYear($fiscalYear)
            ->with([
                'subStrategy.strategy.pillar',
                'agencyOutcome',
                'divisions',
                'actuals',
            ])
            ->orderBy('id')
            ->get();

        $html = view('opcr.pdf', [
            'fiscalYear' => $fiscalYear,
            'settings' => OpcrSetting::current(),
            'indicators' => $indicators,
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

        $mpdf->SetTitle('OPCR FY ' . $fiscalYear);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'OPCR_FY' . $fiscalYear . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => strlen($pdfBytes),
        ]);
    }
}
