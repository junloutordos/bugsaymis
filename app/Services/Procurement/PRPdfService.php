<?php

namespace App\Services\Procurement;

use App\Models\Procurement;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PRPdfService
{
    public function stream(Procurement $pr): StreamedResponse
    {
        $pr->loadMissing([
            'items',
            'requester',
            'division',
            'divisionChief',
            'procurementOfficer',
            'ocd',
        ]);

        $qrSvg = base64_encode(
            QrCode::format('svg')->size(80)->margin(0)->generate(route('procurements.show', $pr->id))
        );

        $html = view('procurement.pr-pdf', compact('pr', 'qrSvg'))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Purchase Request — ' . ($pr->assigned_pr_number ?: $pr->pr_no));
        $mpdf->WriteHTML($html);

        $bytes    = $mpdf->Output('', 'S');
        $filename = 'PR_' . ($pr->assigned_pr_number ?: $pr->pr_no) . '.pdf';

        return new StreamedResponse(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
