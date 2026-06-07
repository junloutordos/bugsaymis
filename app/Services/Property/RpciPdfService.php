<?php

namespace App\Services\Property;

use Mpdf\Mpdf;

class RpciPdfService
{
    public function generate(array $data, string $asOf): \Illuminate\Http\Response
    {
        $mpdf = new Mpdf(['format' => 'A4', 'orientation' => 'L',
            'margin_top' => 15, 'margin_bottom' => 15,
            'margin_left' => 12, 'margin_right' => 12,
            'tempDir' => sys_get_temp_dir()]);
        $mpdf->SetTitle("RPCI as of {$asOf}");
        $mpdf->WriteHTML(view('property.rpci-pdf', ['items' => $data, 'as_of' => $asOf])->render());
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"RPCI-{$asOf}.pdf\"",
        ]);
    }
}
