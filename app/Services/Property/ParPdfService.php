<?php

namespace App\Services\Property;

use App\Models\Property\PropertyAcknowledgmentReceipt;
use Mpdf\Mpdf;

class ParPdfService
{
    public function generate(PropertyAcknowledgmentReceipt $par): \Illuminate\Http\Response
    {
        $mpdf = new Mpdf(['format' => 'A4', 'orientation' => 'P',
            'margin_top' => 15, 'margin_bottom' => 15,
            'margin_left' => 15, 'margin_right' => 15,
            'tempDir' => sys_get_temp_dir()]);
        $mpdf->SetTitle("PAR {$par->par_number}");
        $mpdf->WriteHTML(view('property.par-pdf', ['par' => $par])->render());
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"PAR-{$par->par_number}.pdf\"",
        ]);
    }
}
