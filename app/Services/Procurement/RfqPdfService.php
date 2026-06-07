<?php

namespace App\Services\Procurement;

use App\Models\Procurement\Rfq;
use App\Models\Procurement\RfqSupplier;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RfqPdfService
{
    public function streamRfq(Rfq $rfq): StreamedResponse
    {
        $rfq->loadMissing(['pr.division', 'pr.requester', 'items', 'creator']);

        $qrSvg = base64_encode(
            QrCode::format('svg')->size(70)->margin(0)->generate(
                route('rfq.show', $rfq->id)
            )
        );

        $html = view('procurement.rfq-pdf', compact('rfq', 'qrSvg'))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('RFQ — ' . $rfq->rfq_number);
        $mpdf->WriteHTML($html);

        $bytes    = $mpdf->Output('', 'S');
        $filename = 'RFQ_' . $rfq->rfq_number . '.pdf';

        return new StreamedResponse(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    public function streamNoa(Rfq $rfq, RfqSupplier $supplier): StreamedResponse
    {
        $rfq->loadMissing(['pr.division', 'items']);
        $supplier->loadMissing(['itemQuotations.item']);

        $awardedQuotations = $supplier->itemQuotations->where('is_awarded', true);

        $qrSvg = base64_encode(
            QrCode::format('svg')->size(70)->margin(0)->generate(
                route('rfq.show', $rfq->id)
            )
        );

        $html = view('procurement.noa-pdf', compact('rfq', 'supplier', 'awardedQuotations', 'qrSvg'))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 20,
            'margin_right'  => 20,
            'margin_top'    => 20,
            'margin_bottom' => 20,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Notice of Award — ' . $rfq->rfq_number);
        $mpdf->WriteHTML($html);

        $bytes    = $mpdf->Output('', 'S');
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $supplier->supplier_name);
        $filename = "NOA_{$rfq->rfq_number}_{$safeName}.pdf";

        return new StreamedResponse(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
