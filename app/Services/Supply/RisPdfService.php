<?php

namespace App\Services\Supply;

use App\Models\Supply\RequisitionIssueSlip;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

class RisPdfService
{
    /**
     * @throws MpdfException
     */
    public function generate(RequisitionIssueSlip $ris): \Illuminate\Http\Response
    {
        $mpdf = new Mpdf([
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle("RIS {$ris->ris_number}");

        $html = view('supply.ris-pdf', ['ris' => $ris])->render();
        $mpdf->WriteHTML($html);

        $filename = "RIS-{$ris->ris_number}.pdf";

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
