<?php

namespace App\Services\HomeroomAttendance;

use App\Models\HomeroomAttendance\AdmissionSlip;
use App\Services\PersonNameFormatter;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionSlipPdfService
{
    public function __construct(private PersonNameFormatter $nameFormatter)
    {
    }

    public function render(AdmissionSlip $slip): StreamedResponse
    {
        $slip->loadMissing(['student', 'section', 'issuedBy']);

        $html = view('homeroom-attendance.admission-slip-pdf', [
            'slip'          => $slip,
            'registrarName' => $slip->issuedBy ? $this->nameFormatter->formal($slip->issuedBy) : null,
        ])->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => [148, 105], // A6 — a small admission slip, not a full page
            'orientation'   => 'L',
            'margin_top'    => 6,
            'margin_bottom' => 6,
            'margin_left'   => 8,
            'margin_right'  => 8,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Class Admission Slip #'.$slip->id);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Class-Admission-Slip-'.$slip->id.'.pdf"',
            'Content-Length'      => strlen($pdfBytes),
        ]);
    }
}
