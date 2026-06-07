<?php

namespace App\Services\Property;

use App\Models\Property\PropertyItem;
use Mpdf\Mpdf;

class DepreciationSchedulePdfService
{
    public function generate(PropertyItem $item, array $schedule): \Illuminate\Http\Response
    {
        $mpdf = new Mpdf(['format' => 'A4', 'orientation' => 'P',
            'margin_top' => 15, 'margin_bottom' => 15,
            'margin_left' => 15, 'margin_right' => 15,
            'tempDir' => sys_get_temp_dir()]);
        $mpdf->SetTitle("Depreciation Schedule — {$item->property_number}");
        $mpdf->WriteHTML(view('property.depreciation-pdf', ['item' => $item, 'schedule' => $schedule])->render());
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"DEP-{$item->property_number}.pdf\"",
        ]);
    }
}
