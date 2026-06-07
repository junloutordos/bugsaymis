<?php

namespace App\Services\Supply;

use App\Models\Supply\InspectionAcceptanceReport;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

class IarPdfService
{
    /**
     * @throws MpdfException
     */
    public function generate(InspectionAcceptanceReport $iar): \Illuminate\Http\Response
    {
        $mpdf = new Mpdf([
            'format'      => 'A4',
            'orientation' => 'P',
            'margin_top'  => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'margin_right' => 15,
            'tempDir'     => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle("IAR {$iar->iar_number}");

        $html = view('supply.iar-pdf', ['iar' => $iar])->render();
        $mpdf->WriteHTML($html);

        $filename = "IAR-{$iar->iar_number}.pdf";

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
