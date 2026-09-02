<?php

namespace App\Services\PerformanceManagementV2;

use App\Models\PM2\EmployeeIpcrV2;
use Mpdf\Mpdf;

class IpcrPdfServiceV2
{
    public function generate(EmployeeIpcrV2 $ipcr): \Illuminate\Http\Response
    {
        $ipcr->loadMissing(['user.division', 'ratingPeriod', 'rows.plan.performanceIndicator.agencyOutcome', 'rows.templateItem']);

        $mpdf = new Mpdf([
            'format' => 'A4', 'orientation' => 'L',
            'margin_top' => 12, 'margin_bottom' => 12, 'margin_left' => 10, 'margin_right' => 10,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetTitle("IPCR — {$ipcr->user->name} — {$ipcr->ratingPeriod?->label}");
        $mpdf->WriteHTML(view('pm2.ipcr-pdf', ['ipcr' => $ipcr])->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"IPCR-{$ipcr->id}.pdf\"",
        ]);
    }
}
