<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpDocument;
use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgramCycle;
use App\Models\ALP\AlpReport;
use App\Models\ALP\AlpSession;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class AlpPdfService
{
    public function document(AlpDocument $document): string
    {
        $document->loadMissing($this->cycleRelations('cycle'));

        return $this->render(view('alp.document', ['document' => $document, 'cycle' => $document->cycle])->render());
    }

    public function package(AlpProgramCycle $cycle): string
    {
        $cycle->loadMissing($this->cycleRelations());
        $html = view('alp.package', compact('cycle'))->render();

        return $this->render($html);
    }

    public function report(AlpReport $report): string
    {
        $report->loadMissing($this->cycleRelations('cycle'));
        $report->loadMissing(['preparer:id,name', 'reviewer:id,name']);

        return $this->render(view('alp.report', ['report' => $report, 'cycle' => $report->cycle])->render(), true);
    }

    public function certificate(AlpMembership $membership): string
    {
        $membership->loadMissing(['student', 'cycle.program', 'cycle.schoolYear', 'cycle.adviser', 'cycle.coordinator']);
        $html = view('alp.certificate', compact('membership'))->render();

        return $this->render($html, true);
    }

    public function attendance(AlpSession $session): string
    {
        $session->loadMissing([
            'cycle.program', 'cycle.schoolYear', 'cycle.adviser',
            'attendance.membership.student', 'attendance.membership.enrollment.section',
        ]);

        return $this->render(view('alp.attendance', compact('session'))->render(), true);
    }

    private function render(string $html, bool $landscape = false): string
    {
        $mpdf = new Mpdf([
            'format' => 'A4'.($landscape ? '-L' : ''),
            'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function cycleRelations(string $prefix = ''): array
    {
        $p = $prefix ? $prefix.'.' : '';

        return [
            $p.'program', $p.'schoolYear', $p.'adviser', $p.'coordinator',
            $p.'memberships.student', $p.'memberships.enrollment.section',
            $p.'officers.membership.student', $p.'officers.membership.enrollment.section',
            $p.'activities', $p.'financialEntries',
            $p.'approvalSnapshots.user',
        ];
    }
}
