<?php

namespace App\Services\Procurement;

use App\Models\Procurement\DisbursementVoucher;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DVPdfService
{
    public function stream(DisbursementVoucher $dv): StreamedResponse
    {
        $dv->loadMissing([
            'ors',
            'creator',
            'preparedByUser',
            'divisionChief',
            'bookkeeper',
            'accountant',
            'ocdSigner',
            'cashier',
        ]);

        $qrSvg = base64_encode(
            QrCode::format('svg')->size(80)->margin(0)->generate(route('dv.show', $dv->id))
        );

        $html = view('procurement.dv-pdf', compact('dv', 'qrSvg'))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Disbursement Voucher — ' . ($dv->dv_number ?: 'Draft'));
        $mpdf->WriteHTML($html);

        $bytes    = $mpdf->Output('', 'S');
        $filename = 'DV_' . ($dv->dv_number ?: $dv->id) . '.pdf';

        return new StreamedResponse(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
