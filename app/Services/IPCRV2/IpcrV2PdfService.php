<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IpcrV2PdfService
{
    public function __construct(
        private StrategicFunctionService $strategic = new StrategicFunctionService(),
        private IpcrV2SummaryService $summary = new IpcrV2SummaryService(),
        private IPCRWorkflowService $chain = new IPCRWorkflowService()
    ) {}

    public function stream(IpcrV2Record $record): StreamedResponse
    {
        $record->loadMissing(['user', 'coreItems', 'supportItems', 'period']);

        $supervisor = $this->chain->immediateSupervisorFor($record->user)
            ?? ($record->user->hasRole('DivisionChief') ? User::havingRole('OCD')->first() : null);
        $ocdUser = User::havingRole('OCD')->first();

        $html = view('ipcr-v2.pdf', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'summary' => $this->summary->buildRows($record),
            'supervisor' => $supervisor,
            'ocdUser' => $ocdUser,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('IPCR V2 — ' . $record->user->name . ' — ' . $record->period->label);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'IPCRV2_' . str_replace(' ', '_', $record->user->name) . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => strlen($pdfBytes),
        ]);
    }
}
