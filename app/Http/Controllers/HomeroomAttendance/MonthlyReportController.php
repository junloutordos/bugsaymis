<?php

namespace App\Http\Controllers\HomeroomAttendance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Section;
use App\Models\HomeroomAttendance\MonthlyReport;
use App\Models\User;
use App\Services\HomeroomAttendance\MonthlyReportPdfService;
use App\Services\HomeroomAttendance\MonthlyReportService;
use App\Services\HomeroomAttendance\RosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyReportController extends Controller
{
    public function __construct(
        private RosterService $roster,
        private MonthlyReportService $reports,
        private MonthlyReportPdfService $pdf,
    ) {
    }

    // ── GET /homeroom-attendance/monthly-report ────────────────────────────────

    public function show(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $sections = $this->roster->accessibleSections($user, $term->id);
        abort_if($sections->isEmpty(), 403, 'You have no homeroom advisory sections assigned.');

        $sectionId = (int) $request->query('section', $sections->first()->id);
        abort_unless($sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $report = MonthlyReport::where('section_id', $sectionId)
            ->where('year', $year)
            ->where('month', $month)
            ->with('lines.student')
            ->first();

        return Inertia::render('HomeroomAttendance/MonthlyReport/Show', [
            'sections'  => $sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->sectionname, 'level' => $s->levelid]),
            'sectionId' => $sectionId,
            'year'      => $year,
            'month'     => $month,
            'report'    => $report ? $this->present($report) : null,
        ]);
    }

    // ── POST /homeroom-attendance/monthly-report/generate ──────────────────────

    public function generate(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'year'       => ['required', 'integer'],
            'month'      => ['required', 'integer', 'between:1,12'],
        ]);

        abort_unless(
            $this->roster->canAccessSection($user, (int) $data['section_id'], $term->id),
            403,
            'You do not have access to that section.',
        );

        $report = $this->reports->generate((int) $data['section_id'], $sy->id, (int) $data['year'], (int) $data['month']);

        return redirect()
            ->route('homeroom-attendance.monthly-report.show', ['section' => $data['section_id'], 'year' => $data['year'], 'month' => $data['month']])
            ->with('success', 'Report generated from logged attendance for the month.')
            ->with('report_id', $report->id);
    }

    // ── POST /homeroom-attendance/monthly-report/{report}/submit ───────────────

    public function submit(MonthlyReport $report)
    {
        $user = Auth::user();
        $term = $this->roster->currentAcademicTerm($this->roster->currentSchoolYear());
        abort_unless($this->roster->canAccessSection($user, $report->section_id, $term->id), 403);

        $this->reports->submit($report, $user->id);

        return back()->with('success', 'Report submitted to the Homeroom Unit Coordinator.');
    }

    // ── GET /homeroom-attendance/coordinator ────────────────────────────────────

    public function coordinatorQueue(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $sectionIds = $this->roster->scopedSectionIds($user, $term->id);
        abort_if(empty($sectionIds), 403, 'You have no coordinator scope assigned.');

        $reports = MonthlyReport::whereIn('section_id', $sectionIds)
            ->whereIn('status', ['submitted', 'approved'])
            ->with('section:id,sectionname,levelid', 'submittedBy:id,name')
            ->orderByDesc('submitted_at')
            ->get();

        return Inertia::render('HomeroomAttendance/Coordinator/Review', [
            'reports' => $reports->map(fn (MonthlyReport $r) => [
                'id'           => $r->id,
                'section'      => $r->section?->sectionname,
                'level'        => $r->section?->levelid,
                'year'         => $r->year,
                'month'        => $r->month,
                'status'       => $r->status,
                'submitted_by' => $r->submittedBy?->name,
                'submitted_at' => $r->submitted_at?->toDateTimeString(),
            ])->values(),
        ]);
    }

    // ── POST /homeroom-attendance/monthly-report/{report}/approve ──────────────

    public function approve(Request $request, MonthlyReport $report)
    {
        $user = Auth::user();
        abort_unless($this->canApprove($user, $report), 403, 'You are not the Homeroom Unit Coordinator for this section.');

        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        $this->reports->approve($report, $user->id, $data['remarks'] ?? null);

        return back()->with('success', 'Report approved and filed.');
    }

    // ── POST /homeroom-attendance/monthly-report/{report}/return ───────────────

    public function returnToDraft(Request $request, MonthlyReport $report)
    {
        $user = Auth::user();
        abort_unless($this->canApprove($user, $report), 403, 'You are not the Homeroom Unit Coordinator for this section.');

        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        $this->reports->returnToDraft($report, $data['remarks']);

        return back()->with('success', 'Report returned to the Homeroom Adviser.');
    }

    // ── GET /homeroom-attendance/monthly-report/{report}/print ─────────────────

    public function print(MonthlyReport $report): StreamedResponse
    {
        $report->loadMissing('section');
        $user = Auth::user();
        $term = $this->roster->currentAcademicTerm($this->roster->currentSchoolYear());

        abort_unless(
            $user->hasPermission('homeroom-attendance.admin')
                || $this->roster->canAccessSection($user, $report->section_id, $term->id),
            403,
            'You do not have access to that section.',
        );

        $adviser = $report->submitted_by ? User::find($report->submitted_by) : null;
        $coordinator = $report->approved_by ? User::find($report->approved_by) : null;

        return $this->pdf->render($report, $report->section, $adviser, $coordinator);
    }

    private function canApprove(User $user, MonthlyReport $report): bool
    {
        if ($user->hasPermission('homeroom-attendance.admin')) {
            return true;
        }

        $term = $this->roster->currentAcademicTerm($this->roster->currentSchoolYear());

        return $report->submitted_by !== $user->id
            && $this->roster->canAccessSection($user, $report->section_id, $term->id);
    }

    private function present(MonthlyReport $report): array
    {
        return [
            'id'                  => $report->id,
            'status'              => $report->status,
            'school_days_count'   => $report->school_days_count,
            'submitted_at'        => $report->submitted_at?->toDateTimeString(),
            'approved_at'         => $report->approved_at?->toDateTimeString(),
            'coordinator_remarks' => $report->coordinator_remarks,
            'lines'               => $report->lines->map(fn ($line) => [
                'student_id'            => $line->student_id,
                'student_name'          => trim("{$line->student->lastname}, {$line->student->firstname} {$line->student->middlename}"),
                'sex'                   => $line->student->sex,
                'days_present'          => (float) $line->days_present,
                'excused_absences'      => $line->excused_absences,
                'unexcused_absences'    => $line->unexcused_absences,
                'cutting_count'         => $line->cutting_count,
                'tardy_count'           => $line->tardy_count,
                'inc_uniform_count'     => $line->inc_uniform_count,
                'remarks'               => $line->remarks,
                'is_perfect_attendance' => $line->is_perfect_attendance,
            ])->values(),
        ];
    }
}
