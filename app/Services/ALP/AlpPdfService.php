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

    public function membersList(array $members, string $schoolYearName, ?string $filterLabel = null): string
    {
        return $this->renderWithLetterhead(view('alp.members-list', compact('members', 'schoolYearName', 'filterLabel'))->render());
    }

    public function unassignedList(array $students, string $schoolYearName, ?string $filterLabel = null): string
    {
        return $this->renderWithLetterhead(view('alp.unassigned-list', compact('students', 'schoolYearName', 'filterLabel'))->render());
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

    /**
     * Same repeating letterhead used for WFH accomplishment printing and other
     * list-report PDFs (e.g. ITJobRequestPdfService::exportList) — full-width
     * header/footer images on every page, with content padded independently.
     */
    private function renderWithLetterhead(string $html, bool $landscape = false): string
    {
        $headerPath = public_path('images/report_header.jpeg');
        $footerPath = public_path('images/report_footer.jpeg');
        $pageWidthMm = $landscape ? 297 : 210;

        $headerInfo = @getimagesize($headerPath);
        $footerInfo = @getimagesize($footerPath);
        $headerMm = $headerInfo ? round(($headerInfo[1] / $headerInfo[0]) * $pageWidthMm) + 3 : 36;
        $footerMm = $footerInfo ? round(($footerInfo[1] / $footerInfo[0]) * $pageWidthMm) + 3 : 36;

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4'.($landscape ? '-L' : ''),
            'margin_left' => 0, 'margin_right' => 0,
            'margin_top' => $headerMm, 'margin_bottom' => $footerMm,
            'margin_header' => 0, 'margin_footer' => 0,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetHTMLHeader('<img src="'.$headerPath.'" style="width:100%; display:block;">');
        $mpdf->SetHTMLFooter('<img src="'.$footerPath.'" style="width:100%; display:block;">');
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
