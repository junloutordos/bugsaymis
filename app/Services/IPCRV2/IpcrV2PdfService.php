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
        $html = $this->renderHtml($record);

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

    public function renderHtml(IpcrV2Record $record): string
    {
        $record->loadMissing(['user', 'coreItems', 'supportItems', 'period']);

        $this->annotateFunctionRowspan($record->coreItems);
        $this->annotateFunctionRowspan($record->supportItems);

        $supervisor = $this->chain->immediateSupervisorFor($record->user)
            ?? ($record->user->hasRole('DivisionChief') ? User::havingRole('OCD')->first() : null);
        $ocdUser = User::havingRole('OCD')->first();

        return view('ipcr-v2.pdf', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'summary' => $this->summary->buildRows($record),
            'supervisor' => $supervisor,
            'ocdUser' => $ocdUser,
        ])->render();
    }

    /**
     * Attaches function_rowspan to each item (mirroring
     * StrategicFunctionService's strategy_rowspan convention) so consecutive
     * items sharing the same employee_function_id — one Core/Support
     * Function tagged to N WDPs materializes into N items — merge into one
     * Function/label cell in the PDF, the same way the Show page's tables
     * merge them on screen. The first item of a group gets the span count;
     * the rest get 0, which the view's @if skips.
     */
    private function annotateFunctionRowspan($items): void
    {
        $count = $items->count();
        $i = 0;

        while ($i < $count) {
            $functionId = $items[$i]->employee_function_id;
            $groupSize = 1;

            while ($functionId !== null && $i + $groupSize < $count && $items[$i + $groupSize]->employee_function_id === $functionId) {
                $groupSize++;
            }

            $items[$i]->setAttribute('function_rowspan', $groupSize);
            for ($j = 1; $j < $groupSize; $j++) {
                $items[$i + $j]->setAttribute('function_rowspan', 0);
            }

            $i += $groupSize;
        }
    }
}
