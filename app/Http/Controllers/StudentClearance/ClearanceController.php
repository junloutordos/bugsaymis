<?php

namespace App\Http\Controllers\StudentClearance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearancePeriod;
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
