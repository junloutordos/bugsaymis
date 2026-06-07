<?php

namespace App\Services\Procurement;

use App\Models\Procurement\OblRequest;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ORSPdfService
{
    public function stream(OblRequest $ors): StreamedResponse
    {
        $ors->loadMissing([
            'procurement',
            'creator',
            'division',
            'divisionChief',
            'budgetOfficer',
            'bookkeeper',
            'accountant',
            'ocd',
        ]);

        $qrSvg = base64_encode(
            QrCode::format('svg')->size(80)->margin(0)->generate(route('ors.show', $ors->id))
        );

        $html = view('procurement.ors-pdf', compact('ors', 'qrSvg'))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('ORS — ' . ($ors->ors_number ?: 'Draft'));
        $mpdf->WriteHTML($html);

        $bytes    = $mpdf->Output('', 'S');
        $filename = 'ORS_' . ($ors->ors_number ?: $ors->id) . '.pdf';

        return new StreamedResponse(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
