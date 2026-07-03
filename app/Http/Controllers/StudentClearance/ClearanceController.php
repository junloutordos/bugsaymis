<?php

namespace App\Http\Controllers\StudentClearance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearanceItem;
use App\Models\StudentClearance\StudentClearancePeriod;
use App\Models\StudentClearance\StudentClearanceRequirementSetting;
use App\Models\User;
use App\Services\StudentClearance\StudentClearancePdfService;
use App\Services\StudentClearance\StudentClearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClearanceController extends Controller
{
    public function index(Request $request, StudentClearanceService $service): Response
    {
        $this->authorize('students.clearance.view');

        $currentSchoolYearId = $service->currentSchoolYearId();
        $periodId = $request->integer('period_id');
        $period = $periodId
            ? StudentClearancePeriod::with('schoolYear:id,name,is_current')->find($periodId)
            : $service->activeOrLatestPeriod($currentSchoolYearId);

        $clearanceQuery = StudentClearance::query()
            ->with(['section:id,sectionname,levelid', 'adviser:id,name', 'items:id,student_clearance_id,status,requirement_group'])
            ->when($period, fn ($q) => $q->where('student_clearance_period_id', $period->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('grade_level'), fn ($q) => $q->where('grade_level', $request->integer('grade_level')))
            ->orderBy('grade_level')
            ->orderBy('section_id')
            ->latest('id')
            ->limit(200);

        $clearances = $clearanceQuery->get();
        $students = Student::whereIn('id', $clearances->pluck('student_id')->unique())
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID'])
            ->keyBy('id');

        return Inertia::render('StudentClearance/Index', [
            'schoolYears' => SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']),
            'periods'     => StudentClearancePeriod::withCount('clearances')->with('schoolYear:id,name')->latest('id')->get(),
            'period'      => $period ? $this->serializePeriod($period) : null,
            'filters'     => [
                'period_id'   => $period?->id,
                'status'      => $request->input('status'),
                'grade_level' => $request->input('grade_level'),
            ],
            'stats'       => $period ? $this->stats($period) : null,
            'clearances'  => $clearances->map(fn ($clearance) => $this->serializeListClearance($clearance, $students->get($clearance->student_id))),
            'requirementSettings' => StudentClearanceRequirementSetting::with('assignedUser:id,name')
                ->orderBy('sort_order')
                ->orderBy('requirement_label')
                ->get()
                ->map(fn ($setting) => $this->serializeRequirementSetting($setting)),
            'users' => User::where('status', '<>', 'inactive')
                ->orderBy('name')
                ->get(['id', 'name']),
            'permissionOptions' => Permission::orderBy('name')->pluck('name'),
            'canManageSettings' => $request->user()->hasAnyPermission(['students.clearance.manage', 'students.clearance.admin']),
        ]);
    }

    public function storePeriod(Request $request, StudentClearanceService $service): RedirectResponse
    {
        $this->authorize('students.clearance.manage');

        $data = $request->validate([
            'school_year_id'          => ['required', 'integer', 'exists:school_years,id'],
            'title'                   => ['required', 'string', 'max:255'],
            'opens_at'                => ['nullable', 'date'],
            'closes_at'               => ['nullable', 'date', 'after_or_equal:opens_at'],
            'status'                  => ['required', 'in:draft,open,closed,archived'],
            'target_grade_levels'     => ['nullable', 'array'],
            'target_grade_levels.*'   => ['integer', 'between:7,12'],
        ]);

        $period = $service->createPeriod($data, $request->user());

        return redirect()->route('student-clearance.index', ['period_id' => $period->id])
            ->with('success', 'Clearance period created.');
    }

    public function generate(StudentClearancePeriod $period, Request $request, StudentClearanceService $service): RedirectResponse
    {
        $this->authorize('students.clearance.manage');

        $result = $service->generateForPeriod($period, $request->user());

        return back()->with('success', "Generated {$result['clearances']} student clearances and {$result['items']} new checklist items.");
    }

    public function updateRequirementSetting(StudentClearanceRequirementSetting $setting, Request $request): RedirectResponse
    {
        $this->authorize('students.clearance.manage');

        $data = $request->validate([
            'requirement_label'       => ['required', 'string', 'max:255'],
            'requirement_group'       => ['required', 'in:laboratory,subject,administrative,final'],
            'assigned_user_id'        => ['nullable', 'integer', 'exists:users,id'],
            'assigned_permission'     => ['nullable', 'string', 'max:100'],
            'applies_grade_levels'    => ['nullable', 'array'],
            'applies_grade_levels.*'  => ['integer', 'between:7,12'],
            'intern_only'             => ['boolean'],
            'is_active'               => ['boolean'],
        ]);

        $setting->update([
            'requirement_label'    => $data['requirement_label'],
            'requirement_group'    => $data['requirement_group'],
            'assigned_user_id'     => $data['assigned_user_id'] ?? null,
            'assigned_permission'  => $data['assigned_permission'] ?: null,
            'applies_grade_levels' => $data['applies_grade_levels'] ?? null,
            'intern_only'          => (bool) ($data['intern_only'] ?? false),
            'is_active'            => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('success', 'Clearance requirement setting updated.');
    }

    public function report(StudentClearancePeriod $period): Response
    {
        $this->authorizeReportAccess();

        $period->load('schoolYear:id,name,is_current');

        return Inertia::render('StudentClearance/Report', [
            'period'        => $this->serializePeriod($period),
            'stats'         => $this->stats($period),
            'byStatus'      => $this->reportByStatus($period),
            'bySection'     => $this->reportBySection($period),
            'byRequirement' => $this->reportByRequirement($period),
        ]);
    }

    public function export(StudentClearancePeriod $period): SymfonyResponse
    {
        $this->authorizeReportAccess();

        $clearances = StudentClearance::query()
            ->where('student_clearance_period_id', $period->id)
            ->with(['section:id,sectionname,levelid', 'items:id,student_clearance_id,requirement_label,status,blocker_summary'])
            ->orderBy('grade_level')
            ->orderBy('section_id')
            ->get();

        $students = Student::whereIn('id', $clearances->pluck('student_id')->unique())
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID'])
            ->keyBy('id');

        $filename = 'student-clearance-report-'.$period->id.'.csv';

        return response()->streamDownload(function () use ($clearances, $students) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student', 'PISAY ID', 'Grade', 'Section', 'Status', 'Progress', 'Pending / Held Requirements', 'System Blockers']);

            foreach ($clearances as $clearance) {
                $student = $students->get($clearance->student_id);
                $done = $clearance->items->whereIn('status', ['cleared', 'waived', 'not_applicable'])->count();
                $total = $clearance->items->count();
                $pending = $clearance->items
                    ->reject(fn ($item) => in_array($item->status, ['cleared', 'waived', 'not_applicable'], true))
                    ->map(fn ($item) => $item->requirement_label.' ('.$item->status.')')
                    ->implode('; ');
                $blockers = $clearance->items
                    ->pluck('blocker_summary')
                    ->filter()
                    ->implode('; ');

                fputcsv($out, [
                    $student?->full_name ?? 'Unknown student',
                    $student?->pisaysystemID ?? $clearance->pisaysystem_id,
                    $clearance->grade_level,
                    $clearance->section?->sectionname,
                    $clearance->status,
                    "{$done}/{$total}",
                    $pending,
                    $blockers,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(StudentClearance $clearance): Response
    {
        $this->authorizeClearanceAccess($clearance);

        $clearance->load([
            'period.schoolYear:id,name,is_current',
            'section:id,sectionname,levelid',
            'adviser:id,name',
            'items.assignedUser:id,name',
            'items.signer:id,name',
            'logs',
        ]);

        $student = Student::where('id', $clearance->student_id)->first(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID']);

        return Inertia::render('StudentClearance/Show', [
            'clearance' => $this->serializeDetailClearance($clearance, $student),
        ]);
    }

    public function download(StudentClearance $clearance, StudentClearancePdfService $pdfService): SymfonyResponse
    {
        $this->authorizeClearanceAccess($clearance);

        $student = Student::where('id', $clearance->student_id)->firstOrFail();
        $pdf = $pdfService->generate($clearance);
        $filename = 'Year_End_Clearance_'.str_replace([',', ' '], ['', '_'], $student->full_name).'.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'private, no-cache',
        ]);
    }

    public function adviserReview(StudentClearance $clearance, Request $request, StudentClearanceService $service): RedirectResponse
    {
        $service->adviserReview($clearance, $request->user());

        return back()->with('success', 'Clearance forwarded to the registrar.');
    }

    public function finalize(StudentClearance $clearance, Request $request, StudentClearanceService $service): RedirectResponse
    {
        $service->finalize($clearance, $request->user());

        return back()->with('success', 'Student clearance finalized.');
    }

    private function serializePeriod(StudentClearancePeriod $period): array
    {
        return [
            'id'                  => $period->id,
            'title'               => $period->title,
            'school_year_id'      => $period->school_year_id,
            'school_year_name'    => $period->schoolYear?->name,
            'status'              => $period->status,
            'opens_at'            => $period->opens_at?->format('Y-m-d'),
            'closes_at'           => $period->closes_at?->format('Y-m-d'),
            'target_grade_levels' => $period->target_grade_levels ?: [],
        ];
    }

    private function authorizeClearanceAccess(StudentClearance $clearance): void
    {
        $user = request()->user();
        $assignedPermissions = $clearance->items()
            ->whereNotNull('assigned_permission')
            ->pluck('assigned_permission')
            ->unique()
            ->values()
            ->all();

        abort_unless(
            $user->hasAnyPermission(['students.clearance.view', 'students.clearance.manage', 'students.clearance.registrar', 'students.clearance.admin'])
            || $clearance->adviser_id === $user->id
            || $clearance->items()->where('assigned_user_id', $user->id)->exists()
            || ($assignedPermissions !== [] && $user->hasAnyPermission($assignedPermissions)),
            403
        );
    }

    private function authorizeReportAccess(): void
    {
        abort_unless(request()->user()->hasAnyPermission([
            'students.clearance.report',
            'students.clearance.view',
            'students.clearance.manage',
            'students.clearance.registrar',
            'students.clearance.admin',
        ]), 403);
    }

    private function stats(StudentClearancePeriod $period): array
    {
        $byStatus = StudentClearance::where('student_clearance_period_id', $period->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total'      => $byStatus->sum(),
            'cleared'    => (int) $byStatus->get('cleared', 0),
            'pending'    => (int) ($byStatus->sum() - $byStatus->get('cleared', 0)),
            'by_status'  => $byStatus,
        ];
    }

    private function reportByStatus(StudentClearancePeriod $period): array
    {
        return StudentClearance::where('student_clearance_period_id', $period->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total'  => (int) $row->total,
            ])
            ->all();
    }

    private function reportBySection(StudentClearancePeriod $period): array
    {
        return StudentClearance::query()
            ->leftJoin('sections', 'sections.id', '=', 'student_clearances.section_id')
            ->where('student_clearance_period_id', $period->id)
            ->selectRaw('student_clearances.grade_level, student_clearances.section_id, sections.sectionname, COUNT(*) as total')
            ->selectRaw("SUM(student_clearances.status = 'cleared') as cleared")
            ->selectRaw("SUM(student_clearances.status <> 'cleared') as pending")
            ->groupBy('student_clearances.grade_level', 'student_clearances.section_id', 'sections.sectionname')
            ->orderBy('student_clearances.grade_level')
            ->orderBy('sections.sectionname')
            ->get()
            ->map(fn ($row) => [
                'grade_level'  => (int) $row->grade_level,
                'section_name' => $row->sectionname,
                'total'        => (int) $row->total,
                'cleared'      => (int) $row->cleared,
                'pending'      => (int) $row->pending,
            ])
            ->all();
    }

    private function reportByRequirement(StudentClearancePeriod $period): array
    {
        return StudentClearanceItem::query()
            ->join('student_clearances', 'student_clearances.id', '=', 'student_clearance_items.student_clearance_id')
            ->where('student_clearances.student_clearance_period_id', $period->id)
            ->selectRaw('student_clearance_items.requirement_label, student_clearance_items.requirement_group, COUNT(*) as total')
            ->selectRaw("SUM(student_clearance_items.status IN ('cleared', 'waived', 'not_applicable')) as completed")
            ->selectRaw("SUM(student_clearance_items.status = 'pending') as pending")
            ->selectRaw("SUM(student_clearance_items.status = 'hold') as hold_count")
            ->selectRaw("SUM(student_clearance_items.status = 'returned') as returned_count")
            ->selectRaw('SUM(student_clearance_items.blocker_summary IS NOT NULL) as blocker_count')
            ->groupBy('student_clearance_items.requirement_label', 'student_clearance_items.requirement_group')
            ->orderBy('student_clearance_items.requirement_group')
            ->orderBy('student_clearance_items.requirement_label')
            ->get()
            ->map(fn ($row) => [
                'requirement_label' => $row->requirement_label,
                'requirement_group' => $row->requirement_group,
                'total'             => (int) $row->total,
                'completed'         => (int) $row->completed,
                'pending'           => (int) $row->pending,
                'hold'              => (int) $row->hold_count,
                'returned'          => (int) $row->returned_count,
                'blockers'          => (int) $row->blocker_count,
            ])
            ->all();
    }

    private function serializeRequirementSetting(StudentClearanceRequirementSetting $setting): array
    {
        return [
            'id'                   => $setting->id,
            'requirement_code'     => $setting->requirement_code,
            'requirement_label'    => $setting->requirement_label,
            'requirement_type'     => $setting->requirement_type,
            'requirement_group'    => $setting->requirement_group,
            'assigned_user_id'     => $setting->assigned_user_id,
            'assigned_user_name'   => $setting->assignedUser?->name,
            'assigned_permission'  => $setting->assigned_permission,
            'applies_grade_levels' => $setting->applies_grade_levels ?: [],
            'intern_only'          => $setting->intern_only,
            'is_active'            => $setting->is_active,
            'sort_order'           => $setting->sort_order,
        ];
    }

    private function serializeListClearance(StudentClearance $clearance, ?Student $student): array
    {
        $totalItems = $clearance->items->count();
        $doneItems = $clearance->items->whereIn('status', ['cleared', 'waived', 'not_applicable'])->count();

        return [
            'id'           => $clearance->id,
            'student_name' => $student?->full_name ?? 'Unknown student',
            'pisays_id'    => $student?->pisaysystemID ?? $clearance->pisaysystem_id,
            'grade_level'  => $clearance->grade_level,
            'section_name' => $clearance->section?->sectionname,
            'adviser_name' => $clearance->adviser?->name,
            'status'       => $clearance->status,
            'progress'     => [
                'done'  => $doneItems,
                'total' => $totalItems,
            ],
        ];
    }

    private function serializeDetailClearance(StudentClearance $clearance, ?Student $student): array
    {
        return [
            'id'           => $clearance->id,
            'student_name' => $student?->full_name ?? 'Unknown student',
            'pisays_id'    => $student?->pisaysystemID ?? $clearance->pisaysystem_id,
            'status'       => $clearance->status,
            'grade_level'  => $clearance->grade_level,
            'section_name' => $clearance->section?->sectionname,
            'adviser_name' => $clearance->adviser?->name,
            'period'       => $this->serializePeriod($clearance->period),
            'items'        => $clearance->items->map(fn ($item) => [
                'id'                => $item->id,
                'requirement_label' => $item->requirement_label,
                'requirement_type'  => $item->requirement_type,
                'requirement_group' => $item->requirement_group,
                'status'            => $item->status,
                'remarks'           => $item->remarks,
                'accountability'    => $item->accountability,
                'blocker_summary'   => $item->blocker_summary,
                'assigned_to'       => $item->assignedUser?->name,
                'assigned_permission' => $item->assigned_permission,
                'signed_by'         => $item->signer?->name,
                'signed_at'         => $item->signed_at?->format('Y-m-d H:i'),
            ])->values(),
        ];
    }
}
