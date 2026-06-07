<?php

namespace App\Services\Procurement;

use App\Models\Procurement\PurchaseOrder;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderPdfService
{
    public function stream(PurchaseOrder $po): StreamedResponse
    {
        $po->loadMissing(['pr', 'creator', 'procurementOfficer', 'ocd', 'items']);

        $qrSvg = base64_encode(
            QrCode::format('svg')->size(80)->margin(0)->generate(route('po.show', $po->id))
        );

        $html = view('procurement.po-pdf', compact('po', 'qrSvg'))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('PO — ' . ($po->po_number ?: 'Draft'));
        $mpdf->WriteHTML($html);

        $bytes    = $mpdf->Output('', 'S');
        $filename = 'PO_' . ($po->po_number ?: $po->id) . '.pdf';

        return new StreamedResponse(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
