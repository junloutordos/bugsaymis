<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Office;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use App\Services\PersonNameFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FacultyLoadController extends Controller
{
    public function __construct(
        private readonly LoadComputationService $loads,
        private readonly PersonNameFormatter $names,
    ) {
    }

    // ── Admin/CID: all faculty loads for a term ───────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.view');

        $currentTerm = AcademicTerm::with('schoolYear')->where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $loads = FacultyLoad::with(['faculty:id,name,position', 'academicTerm.schoolYear'])
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->orderBy(User::select('name')->whereColumn('users.id', 'faculty_loads.user_id'))
            ->get()
            ->map(fn ($l) => $this->mapLoad($l));

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'label'      => $t->full_label,
                'is_current' => $t->is_current,
            ]);

        return Inertia::render('FacultyLoading/Index', [
            'loads'       => $loads,
            'terms'       => $terms,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => ['term_id' => $termId],
        ]);
    }

    // ── Faculty: own load ─────────────────────────────────────────────────────

    public function myLoad(Request $request): Response
    {
        $this->authorize('faculty_loading.view_own');

        $currentTerm = AcademicTerm::with('schoolYear')->where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $load = FacultyLoad::with(['assignments.subject', 'academicTerm.schoolYear', 'overloadComputation'])
            ->where('user_id', Auth::id())
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->first();

        $terms = AcademicTerm::with('schoolYear')
            ->whereHas('facultyLoads', fn ($q) => $q->where('user_id', Auth::id()))
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'label'      => $t->full_label,
                'is_current' => $t->is_current,
            ]);

        $sectionMap = DB::table('sections')->pluck('sectionname', 'id')->all();
        $loadData   = $load ? $this->mapLoad($load, true, $sectionMap) : null;

        return Inertia::render('FacultyLoading/MyLoad', [
            'load'        => $loadData,
            'terms'       => $terms,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => ['term_id' => $termId],
        ]);
    }

    // ── Campus Director: approve overload ────────────────────────────────────

    public function approveOverload(Request $request, FacultyLoad $facultyLoad): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'approved'         => 'required|boolean',
            'approval_remarks' => 'nullable|string|max:500',
        ]);

        $facultyLoad->update([
            'overload_approved' => $data['approved'],
            'approved_by'       => Auth::id(),
            'approved_at'       => now(),
            'approval_remarks'  => $data['approval_remarks'] ?? null,
        ]);

        $action = $data['approved'] ? 'approved' : 'rejected';

        return back()->with('success', "Overload {$action} for {$facultyLoad->faculty?->name}.");
    }

    // ── Lock / unlock a faculty load ─────────────────────────────────────────

    public function lockLoad(FacultyLoad $facultyLoad): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $facultyLoad->update(['is_locked' => true]);

        return back()->with('success', "Load record locked for {$facultyLoad->faculty?->name}.");
    }

    public function unlockLoad(FacultyLoad $facultyLoad): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $facultyLoad->update(['is_locked' => false]);

        return back()->with('success', "Load record unlocked for {$facultyLoad->faculty?->name}.");
    }

    // ── Print: individual (admin/CID) ────────────────────────────────────────

    public function print(FacultyLoad $facultyLoad): Response
    {
        $this->authorize('faculty_loading.view');

        $facultyLoad->load($this->printRelations());

        [$sectionMap, $cidChief, $director] = $this->printDependencies();

        return Inertia::render('FacultyLoading/Print', [
            'loads' => [$this->mapLoadForPrint($facultyLoad, $sectionMap, $cidChief, $director)],
        ]);
    }

    // ── Print: own load (faculty) ─────────────────────────────────────────────

    public function printMyLoad(Request $request): Response
    {
        $this->authorize('faculty_loading.view_own');

        $termId = $request->input('term_id', AcademicTerm::where('is_current', true)->value('id'));

        $load = FacultyLoad::with($this->printRelations())
            ->where('user_id', Auth::id())
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->first();

        abort_if(! $load, 404);

        [$sectionMap, $cidChief, $director] = $this->printDependencies();

        return Inertia::render('FacultyLoading/Print', [
            'loads' => [$this->mapLoadForPrint($load, $sectionMap, $cidChief, $director)],
        ]);
    }

    // ── Print: batch (all faculty for a term) ────────────────────────────────

    public function printBatch(Request $request): Response
    {
        $this->authorize('faculty_loading.view');

        $termId = $request->input('term_id', AcademicTerm::where('is_current', true)->value('id'));

        $loads = FacultyLoad::with($this->printRelations())
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->orderBy(User::select('name')->whereColumn('users.id', 'faculty_loads.user_id'))
            ->get();

        [$sectionMap, $cidChief, $director] = $this->printDependencies();

        return Inertia::render('FacultyLoading/Print', [
            'loads' => $loads->map(fn ($l) => $this->mapLoadForPrint($l, $sectionMap, $cidChief, $director))->values(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function printRelations(): array
    {
        return [
            'faculty:id,name,position,office_id',
            'faculty.pds.personalInfo',
            'faculty.office.unitHeadUser',
            'faculty.office.unitHeadUser.pds.personalInfo',
            'academicTerm.schoolYear',
            'assignments.subject',
        ];
    }

    private function printDependencies(): array
    {
        $sectionMap = DB::table('sections')->pluck('sectionname', 'id')->all();

        $cidChief = User::whereHas('roles', fn ($q) => $q->where('name', 'CID Chief'))
            ->with('pds.personalInfo')
            ->first(['id', 'name', 'position']);

        $director = User::where('position', 'like', '%Director%')
            ->where('position', 'not like', '%Assistant%')
            ->with('pds.personalInfo')
            ->first(['id', 'name', 'position']);

        return [$sectionMap, $cidChief, $director];
    }

    /**
     * Print-only override: a Division Chief's office_id points at the
     * division they administratively head (so Office::unitHeadUser
     * resolves to themselves) — but the "Prepared & submitted by" signatory
     * for their own teaching/advisory load should be the head of the unit
     * their actual work falls under, not themselves. Keyed by user_id,
     * value is the office_id whose unit head should sign instead.
     * office_id itself is untouched; this only affects this print.
     */
    private const PRINT_AUH_OFFICE_OVERRIDE = [
        9  => 3,  // Gumapac, Jasmine S. (SSD Chief) -> Social Science Unit (Salang, Keith R.)
        25 => 42, // Fernando, Michelle B. (CID Chief) -> Research Unit (Alerta, Gilbert)
    ];

    private function mapLoadForPrint(FacultyLoad $l, array $sectionMap, ?User $cidChief, ?User $director): array
    {
        $assignments = $l->assignments->map(function ($a) use ($sectionMap) {
            if ($a->assignment_type === 'teaching' && $a->subject) {
                $section = $a->section_id ? ($sectionMap[$a->section_id] ?? 'Sec ' . $a->section_id) : null;
                $label   = $a->subject->name . ($section ? ' (' . $section . ')' : '');
            } else {
                $label = $a->description ?? ucfirst(str_replace('_', ' ', $a->assignment_type));
            }

            return [
                'id'              => $a->id,
                'assignment_type' => $a->assignment_type,
                'display_label'   => $label,
                'load_units'      => (float) $a->load_units,
            ];
        })->values();

        // Resolve AUH from the faculty member's office (Office/Unit under CID Division),
        // unless a print-only override applies (see PRINT_AUH_OFFICE_OVERRIDE).
        $overrideOfficeId = self::PRINT_AUH_OFFICE_OVERRIDE[$l->faculty?->id] ?? null;
        $office           = $overrideOfficeId
            ? Office::with('unitHeadUser.pds.personalInfo')->find($overrideOfficeId)
            : $l->faculty?->office;
        $academicUnitName = $overrideOfficeId ? $l->faculty?->office?->name : $office?->name;
        $auh = $office?->unitHeadUser ? [
            'name'     => $this->names->formal($office->unitHeadUser),
            'position' => $office->unitHeadUser->position ?? 'Academic Unit Head',
        ] : null;

        return [
            'id'                 => $l->id,
            'faculty'            => $l->faculty ? [
                'name'     => $this->names->formal($l->faculty),
                'position' => $l->faculty->position ?? '',
            ] : null,
            'term'               => $l->academicTerm ? [
                'label'       => $l->academicTerm->full_label,
                'school_year' => $l->academicTerm->schoolYear?->name ?? '',
                'start_date'  => $l->academicTerm->start_date?->format('F Y'),
                'end_date'    => $l->academicTerm->end_date?->format('F Y'),
            ] : null,
            'academic_unit_name' => $academicUnitName,
            'assignments'        => $assignments,
            'total_units'        => (float) $l->total_units,
            'signatories'        => [
                'auh'       => $auh,
                'cid_chief' => $cidChief ? ['name' => $this->names->formal($cidChief), 'position' => $cidChief->position ?? 'CID Chief'] : null,
                'director'  => $director  ? ['name' => $this->names->formal($director),  'position' => $director->position  ?? 'Director III'] : null,
            ],
        ];
    }

    private function mapLoad(FacultyLoad $l, bool $withAssignments = false, array $sectionMap = []): array
    {
        $base = [
            'id'                 => $l->id,
            'faculty'            => $l->faculty ? [
                'id'       => $l->faculty->id,
                'name'     => $l->faculty->name,
                'position' => $l->faculty->position ?? null,
            ] : null,
            'term'               => $l->academicTerm ? [
                'id'    => $l->academicTerm->id,
                'label' => $l->academicTerm->full_label,
            ] : null,
            'teaching_units'     => (float) $l->teaching_units,
            'research_units'     => (float) $l->research_units,
            'admin_units'        => (float) $l->admin_units,
            'cocurricular_units' => (float) $l->cocurricular_units,
            'committee_units'    => (float) $l->committee_units,
            'total_units'        => (float) $l->total_units,
            'full_load_threshold'=> (float) $l->full_load_threshold,
            'load_status'        => $l->load_status,
            'is_locked'          => $l->is_locked,
            'overload_approved'  => $l->overload_approved,
            'overload_units'     => $l->overload_units,
            'approved_at'        => $l->approved_at?->toDateTimeString(),
            'approval_remarks'   => $l->approval_remarks,
        ];

        if ($withAssignments) {
            $base['assignments'] = $l->assignments->map(fn ($a) => [
                'id'              => $a->id,
                'assignment_type' => $a->assignment_type,
                'load_units'      => (float) $a->load_units,
                'description'     => $a->description,
                'display_label'   => $a->display_label,
                'section_id'      => $a->section_id,
                'section_name'    => $a->section_id ? ($sectionMap[$a->section_id] ?? null) : null,
                'subject'         => $a->subject ? [
                    'id'   => $a->subject->id,
                    'code' => $a->subject->code,
                    'name' => $a->subject->name,
                ] : null,
            ]);

            $oc = $l->overloadComputation;
            $base['overload_computation'] = $oc ? [
                'id'                 => $oc->id,
                'annual_rate'        => (float) $oc->annual_rate,
                'phtr'               => (float) $oc->phtr,
                'overload_units'     => (float) $oc->overload_units,
                'overload_hours'     => (float) $oc->overload_hours,
                'term_weeks'         => $oc->term_weeks,
                'total_overload_pay' => (float) $oc->total_overload_pay,
                'status'             => $oc->status,
                'approved_at'        => $oc->approved_at?->toDateString(),
            ] : null;
        }

        return $base;
    }
}
