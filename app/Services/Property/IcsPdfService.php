<?php

namespace App\Services\Property;

use App\Models\Property\InventoryCustodianSlip;
use Mpdf\Mpdf;

class IcsPdfService
{
    public function generate(InventoryCustodianSlip $ics): \Illuminate\Http\Response
    {
        $mpdf = new Mpdf(['format' => 'A4', 'orientation' => 'P',
            'margin_top' => 15, 'margin_bottom' => 15,
            'margin_left' => 15, 'margin_right' => 15,
            'tempDir' => sys_get_temp_dir()]);
        $mpdf->SetTitle("ICS {$ics->ics_number}");
        $mpdf->WriteHTML(view('property.ics-pdf', ['ics' => $ics])->render());
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"ICS-{$ics->ics_number}.pdf\"",
        ]);
    }
}
