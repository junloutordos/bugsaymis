<?php

namespace App\Services\Procurement;

use App\Models\Procurement\Rfq;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AoqPdfService
{
    public function streamAoq(Rfq $rfq): StreamedResponse
    {
        $rfq->loadMissing([
            'pr.division',
            'pr.requester',
            'items',
            'suppliers.itemQuotations',
        ]);

        $items     = $rfq->items;
        $suppliers = $rfq->suppliers;

        // Build matrix
        $matrix = $items->map(function ($item) use ($suppliers) {
            $quotations = [];
            $lowestCost = null;

            foreach ($suppliers as $sup) {
                $q = $item->quotations->firstWhere('rfq_supplier_id', $sup->id);
                $cost = $q?->unit_cost > 0 ? (float) $q->unit_cost : null;
                $quotations[$sup->id] = [
                    'unit_cost'  => $cost,
                    'total_cost' => $cost ? $cost * $item->quantity : null,
                    'is_awarded' => (bool) ($q?->is_awarded ?? false),
                ];
                if ($cost && ($lowestCost === null || $cost < $lowestCost)) {
                    $lowestCost = $cost;
                }
            }

            return [
                'item'         => $item,
                'quotations'   => $quotations,
                'lowest_cost'  => $lowestCost,
            ];
        });

        $html = view('procurement.aoq-pdf', compact('rfq', 'suppliers', 'matrix'))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4-L',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Abstract of Quotations — ' . $rfq->rfq_number);
        $mpdf->WriteHTML($html);

        $bytes    = $mpdf->Output('', 'S');
        $filename = 'AoQ_' . $rfq->rfq_number . '.pdf';

        return new StreamedResponse(function () use ($bytes) {
            echo $bytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
