<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoadAssignmentController extends Controller
{
    public function __construct(private readonly LoadComputationService $loads) {}

    // ── List assignments for a faculty member in a term ───────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = (int) $request->input('term_id', $currentTerm?->id);

        // All assignments for the term, grouped by faculty
        $rawAssignments = LoadAssignment::with(['faculty:id,name,position', 'subject:id,code,name,subject_type,grade_level'])
            ->where('academic_term_id', $termId)
            ->orderBy('user_id')
            ->orderBy('assignment_type')
            ->orderBy('id')
            ->get();

        // Faculty load records for the term (for load status / lock info)
        $facultyLoads = FacultyLoad::where('academic_term_id', $termId)
            ->get()
            ->keyBy('user_id');

        // Group into one entry per faculty
        $byFaculty = $rawAssignments
            ->groupBy('user_id')
            ->map(function ($rows, $userId) use ($facultyLoads) {
                $first = $rows->first();
                $fl    = $facultyLoads->get($userId);

                $teaching      = $rows->where('assignment_type', 'teaching');
                $nonTeaching   = $rows->where('assignment_type', '!=', 'teaching');

                return [
                    'faculty_id'       => $userId,
                    'faculty_name'     => $first->faculty?->name ?? '—',
                    'position'         => $first->faculty?->position ?? null,
                    'teaching_units'   => (float) $teaching->sum('load_units'),
                    'other_units'      => (float) $nonTeaching->sum('load_units'),
                    'total_units'      => (float) $rows->sum('load_units'),
                    'load_status'      => $fl?->load_status ?? null,
                    'is_locked'        => (bool) ($fl?->is_locked ?? false),
                    'assignment_count' => $rows->count(),
                    'assignments'      => $rows->map(fn ($a) => $this->mapAssignment($a))->values()->all(),
                ];
            })
            ->sortBy('faculty_name')
            ->values();

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current, 'school_year_id' => $t->school_year_id]);

        $faculty = User::employees()->where('status', '<>', 'inactive')
            ->where(fn ($q) => $q->where('on_study_leave', false)->orWhereNull('on_study_leave'))
            ->orderBy('name')->get(['id', 'name', 'position']);

        $subjects = Subject::active()
            ->orderBy('grade_level')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subject_type', 'load_units', 'grade_level']);

        $currentSyId = SchoolYear::where('is_current', true)->value('id');
        $sections = DB::table('sections')
            ->where('school_year_id', $currentSyId)
            ->where('is_active', true)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(['id', 'sectionname', 'levelid']);

        $sectionMap = $sections->pluck('sectionname', 'id')->all();

        $byFaculty = $byFaculty->map(function ($entry) use ($sectionMap) {
            $entry['assignments'] = array_map(
                fn ($a) => $this->injectSectionName($a, $sectionMap),
                $entry['assignments']
            );
            return $entry;
        })->values();

        return Inertia::render('FacultyLoading/Assignments/Index', [
            'facultyLoads' => $byFaculty,
            'terms'        => $terms,
            'faculty'      => $faculty,
            'subjects'     => $subjects,
            'sections'     => $sections,
            'plans'        => WorkDistributionPlan::orderBy('success_indicator')->get(['id', 'success_indicator', 'rated_by']),
            'currentTerm'  => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'      => $request->only(['term_id']),
        ]);
    }

    // ── Create a new load assignment ──────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'school_year_id'   => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'assignment_type'  => ['required', Rule::in(['teaching', 'research', 'admin', 'cocurricular', 'committee'])],
            'subject_id'       => 'nullable|exists:subjects,id',
            'section_id'       => 'nullable|integer',
            'load_units'       => 'required|numeric|min:0.5|max:30',
            'description'      => 'nullable|string|max:500',
        ]);

        // Teaching requires a subject
        if ($data['assignment_type'] === 'teaching' && empty($data['subject_id'])) {
            return back()->withErrors(['subject_id' => 'A subject is required for teaching assignments.']);
        }

        // Ensure a FacultyLoad record exists for this faculty + term
        $load = $this->loads->findOrCreateFacultyLoad(
            $data['user_id'],
            $data['school_year_id'],
            $data['academic_term_id']
        );

        if ($load->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        try {
            $assignment = LoadAssignment::create([
                'faculty_load_id'  => $load->id,
                'user_id'          => $data['user_id'],
                'school_year_id'   => $data['school_year_id'],
                'academic_term_id' => $data['academic_term_id'],
                'assignment_type'  => $data['assignment_type'],
                'subject_id'       => $data['subject_id'] ?? null,
                'section_id'       => $data['section_id'] ?? null,
                'load_units'       => $data['load_units'],
                'description'      => $data['description'] ?? null,
                'created_by'       => Auth::id(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['subject_id' => 'This subject is already assigned to this section for the selected term.']);
        }

        $this->loads->syncLoad($load);

        return back()->with('success', 'Load assignment created.');
    }

    // ── Update an existing assignment ─────────────────────────────────────────

    public function update(Request $request, LoadAssignment $loadAssignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'assignment_type' => ['required', Rule::in(['teaching', 'research', 'admin', 'cocurricular', 'committee'])],
            'subject_id'      => 'nullable|exists:subjects,id',
            'section_id'      => 'nullable|integer',
            'load_units'      => 'required|numeric|min:0.5|max:30',
            'description'     => 'nullable|string|max:500',
        ]);

        if ($data['assignment_type'] === 'teaching' && empty($data['subject_id'])) {
            return back()->withErrors(['subject_id' => 'A subject is required for teaching assignments.']);
        }

        if ($loadAssignment->facultyLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        $loadAssignment->update([
            'assignment_type' => $data['assignment_type'],
            'subject_id'      => $data['subject_id'] ?? null,
            'section_id'      => $data['section_id'] ?? null,
            'load_units'      => $data['load_units'],
            'description'     => $data['description'] ?? null,
        ]);

        $this->loads->syncLoad($loadAssignment->facultyLoad);

        return back()->with('success', 'Load assignment updated.');
    }

    // ── Delete an assignment ──────────────────────────────────────────────────

    public function destroy(LoadAssignment $loadAssignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $facultyLoad = $loadAssignment->facultyLoad;

        if ($facultyLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        $loadAssignment->delete();

        if ($facultyLoad) {
            $this->loads->syncLoad($facultyLoad);
        }

        return back()->with('success', 'Load assignment removed.');
    }

    // ── Sync all loads for a term ─────────────────────────────────────────────

    public function syncAllLoads(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = (int) $request->input('term_id', $currentTerm?->id);

        FacultyLoad::where('academic_term_id', $termId)
            ->each(fn ($fl) => $this->loads->syncLoad($fl));

        return back()->with('success', 'All faculty load totals re-synced.');
    }

    // ── Link / replace this assignment's Work Distribution Plans ─────────────

    public function syncPlans(Request $request, LoadAssignment $loadAssignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'plan_ids'   => 'nullable|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $loadAssignment->workDistributionPlans()->sync($data['plan_ids'] ?? []);

        return back()->with('success', 'Work Distribution Plans updated for this load assignment.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function mapAssignment(LoadAssignment $a): array
    {
        return [
            'id'              => $a->id,
            'faculty_load_id' => $a->faculty_load_id,
            'assignment_type' => $a->assignment_type,
            'load_units'      => (float) $a->load_units,
            'description'     => $a->description,
            'display_label'   => $a->display_label,
            'section_id'      => $a->section_id,
            'section_name'    => null,
            'plan_ids'        => $a->workDistributionPlans->pluck('id')->toArray(),
            'faculty'         => $a->faculty ? ['id' => $a->faculty->id, 'name' => $a->faculty->name] : null,
            'subject'         => $a->subject ? ['id' => $a->subject->id, 'code' => $a->subject->code, 'name' => $a->subject->name] : null,
            'term'            => $a->academicTerm ? ['id' => $a->academicTerm->id, 'label' => $a->academicTerm->full_label] : null,
        ];
    }

    private function injectSectionName(array $a, array $sectionMap): array
    {
        $a['section_name'] = $a['section_id'] ? ($sectionMap[$a['section_id']] ?? null) : null;
        return $a;
    }
}
