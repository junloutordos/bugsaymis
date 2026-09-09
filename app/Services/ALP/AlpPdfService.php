<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpActivity;
use App\Models\ALP\AlpAttendance;
use App\Models\ALP\AlpDocument;
use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgramCycle;
use App\Models\ALP\AlpReport;
use App\Models\ALP\AlpSession;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class AlpPdfService
{
    /**
     * One dedicated Blade view per official PSHS-00-F-DSA-XX form, matched
     * field-for-field against the controlled document. Replaces the old
     * single generic `document-content.blade.php` conditional.
     */
    private const DOCUMENT_VIEWS = [
        'application' => 'alp.forms.document-24',
        'constitution' => 'alp.forms.document-25',
        'adviser_acceptance' => 'alp.forms.document-26',
        'officers' => 'alp.forms.document-27',
        'officer_certification' => 'alp.forms.document-28',
        'class_list' => 'alp.forms.document-29',
        'calendar' => 'alp.forms.document-30',
        'risk_plan' => 'alp.forms.document-32',
    ];

    public function document(AlpDocument $document): string
    {
        $document->loadMissing($this->cycleRelations('cycle'));
        $cycle = $document->cycle;
        $view = self::DOCUMENT_VIEWS[$document->document_type] ?? null;
        abort_unless($view, 404, 'No form template registered for this document type.');

        return $this->render(view($view, [
            'document' => $document,
            'cycle' => $cycle,
            'signatories' => $this->resolveSignatories($cycle),
            'formCode' => $this->formCode($document->form_code, $document->version_no, $document->revision_no),
        ])->render());
    }

    public function consentForm(AlpMembership $membership, ?AlpActivity $activity = null): string
    {
        $membership->loadMissing(['student', 'enrollment.section', 'cycle.program', 'cycle.schoolYear', 'cycle.adviser']);

        return $this->render(view('alp.forms.consent-31', [
            'membership' => $membership,
            'cycle' => $membership->cycle,
            'activity' => $activity,
            'signatories' => $this->resolveSignatories($membership->cycle),
            'formCode' => 'PSHS-00-F-DSA-31-Ver02-Rev0',
        ])->render());
    }

    public function attendanceGrid(AlpProgramCycle $cycle, string $month): string
    {
        $cycle->loadMissing(['program', 'schoolYear', 'adviser', 'coordinator']);
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $members = $cycle->memberships()->where('status', 'active')
            ->with('student:id,firstname,lastname,middlename,sex')
            ->orderBy('id')->get()
            ->each(fn ($m) => $m->student?->append('full_name'));

        $sessions = AlpSession::where('alp_program_cycle_id', $cycle->id)
            ->whereBetween('session_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('session_date')->get();

        $records = AlpAttendance::whereIn('alp_session_id', $sessions->pluck('id'))
            ->whereIn('alp_membership_id', $members->pluck('id'))
            ->get()
            ->groupBy('alp_membership_id')
            ->map(fn ($rows) => $rows->keyBy('alp_session_id'));

        return $this->render(view('alp.forms.attendance-33-grid', [
            'cycle' => $cycle,
            'members' => $members,
            'sessions' => $sessions,
            'records' => $records,
            'month' => $start,
            'signatories' => $this->resolveSignatories($cycle),
            'formCode' => 'PSHS-00-F-DSA-33-Ver02-Rev0',
        ])->render());
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
        $signatories = $this->resolveSignatories($cycle);

        // Package order must follow the PSHS numeric form sequence, not
        // creation order — form_code strings ("PSHS-00-F-DSA-24" .. "-32")
        // sort correctly as plain strings since every code in range has the
        // same digit width.
        $sections = $cycle->documents->sortBy('form_code')
            ->map(function ($document) use ($cycle, $signatories) {
                $view = self::DOCUMENT_VIEWS[$document->document_type] ?? null;
                if (! $view) {
                    return null; // e.g. parent_consent, which is printed per-member instead.
                }
                $rendered = view($view, [
                    'document' => $document,
                    'cycle' => $cycle,
                    'signatories' => $signatories,
                    'formCode' => $this->formCode($document->form_code, $document->version_no, $document->revision_no),
                ])->renderSections();

                return ['html' => $rendered['content'] ?? '', 'formCode' => $this->formCode($document->form_code, $document->version_no, $document->revision_no)];
            })
            ->filter()
            ->values();

        return $this->render(view('alp.forms._bundle', compact('cycle', 'sections'))->render());
    }

    private const REPORT_VIEWS = [
        'financial' => 'alp.forms.report-34-financial',
        'accomplishment' => 'alp.forms.report-35-accomplishment',
        'attendance_summary' => 'alp.forms.report-36-attendance-summary',
        'coordinator' => 'alp.forms.report-37-coordinator',
    ];

    public function report(AlpReport $report): string
    {
        $report->loadMissing($this->cycleRelations('cycle'));
        $report->loadMissing(['preparer:id,name', 'reviewer:id,name']);
        $view = self::REPORT_VIEWS[$report->report_type] ?? null;

        if (! $view) {
            // adviser_evaluation and any other non-QMS report type keep the old generic layout.
            return $this->render(view('alp.report', ['report' => $report, 'cycle' => $report->cycle])->render(), true);
        }

        return $this->render(view($view, [
            'report' => $report,
            'cycle' => $report->cycle,
            'signatories' => $this->resolveSignatories($report->cycle),
            'formCode' => $this->formCode($report->form_code, $report->version_no, $report->revision_no),
        ])->render());
    }

    public function certificate(AlpMembership $membership): string
    {
        $membership->loadMissing(['student', 'cycle.program', 'cycle.schoolYear', 'cycle.adviser', 'cycle.coordinator']);
        $html = view('alp.certificate', compact('membership'))->render();

        return $this->render($html, true);
    }

    /**
     * Daily per-session attendance slip — a different real workflow from the
     * official monthly PSHS-00-F-DSA-33 grid (attendanceGrid() below), which
     * is the actual controlled form. This one is an internal print-out for a
     * single day's roll call and intentionally carries no PSHS control number.
     */
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

    private function formCode(?string $code, ?int $versionNo, ?int $revisionNo): string
    {
        if (! $code) {
            return '';
        }

        return $code.'-Ver'.str_pad((string) ($versionNo ?? 2), 2, '0', STR_PAD_LEFT).'-Rev'.($revisionNo ?? 0);
    }

    /**
     * Resolves the signatories every official ALP form needs beyond the
     * cycle's own adviser/coordinator columns: President and Treasurer are
     * free-text AlpOfficer positions (no dedicated FK), and the Registrar
     * signatory is whoever most recently certified an officer for this
     * cycle. Roles this module doesn't track structurally (Assistant CID
     * Chief for Student Affairs/DSA Chief, Campus Director) are left blank —
     * same fallback the module already used for unknown signatories.
     */
    private function resolveSignatories(AlpProgramCycle $cycle): array
    {
        $cycle->loadMissing(['officers.membership', 'officers.certifier']);
        $officers = $cycle->officers;
        $findByPosition = fn (string $needle) => $officers->first(fn ($o) => str_contains(strtolower($o->position), $needle));
        $president = $findByPosition('president');
        $treasurer = $findByPosition('treasurer');
        $registrarOfficer = $officers->whereNotNull('certified_at')->sortByDesc('certified_at')->first();

        return [
            'adviser' => $cycle->adviser?->name,
            'coordinator' => $cycle->coordinator?->name,
            'president' => $president?->membership?->student?->full_name,
            'treasurer' => $treasurer?->membership?->student?->full_name,
            'registrar' => $registrarOfficer?->certifier?->name,
            'registrar_certified_at' => $registrarOfficer?->certified_at?->format('F j, Y'),
        ];
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
