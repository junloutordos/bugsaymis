<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
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
        $termId      = $request->input('term_id', $currentTerm?->id);
        $facultyId   = $request->input('faculty_id');

        $assignments = LoadAssignment::with(['faculty:id,name', 'subject:id,code,name', 'academicTerm.schoolYear'])
            ->when($termId,    fn ($q) => $q->where('academic_term_id', $termId))
            ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId))
            ->orderBy('user_id')
            ->orderBy('assignment_type')
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $faculty  = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))->orderBy('name')->get(['id', 'name', 'position']);
        $subjects = Subject::active()->orderBy('code')->get(['id', 'code', 'name', 'subject_type', 'load_units']);
        $sections = DB::table('sections')->orderBy('sectionname')->get(['id', 'sectionname', 'levelid']);

        return Inertia::render('FacultyLoading/Assignments/Index', [
            'assignments' => $assignments,
            'terms'       => $terms,
            'faculty'     => $faculty,
            'subjects'    => $subjects,
            'sections'    => $sections,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'faculty_id']),
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
        $load = $this->findOrCreateLoad(
            $data['user_id'],
            $data['school_year_id'],
            $data['academic_term_id']
        );

        if ($load->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

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

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Find or auto-create the FacultyLoad record for a given faculty + term.
     */
    private function findOrCreateLoad(int $userId, int $schoolYearId, int $termId): FacultyLoad
    {
        return FacultyLoad::firstOrCreate(
            ['user_id' => $userId, 'academic_term_id' => $termId],
            [
                'school_year_id'      => $schoolYearId,
                'teaching_units'      => 0,
                'research_units'      => 0,
                'admin_units'         => 0,
                'cocurricular_units'  => 0,
                'committee_units'     => 0,
                'total_units'         => 0,
                'full_load_threshold' => LoadComputationService::FULL_LOAD_THRESHOLD,
                'load_status'         => 'underload',
                'overload_approved'   => false,
            ]
        );
    }

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
            'faculty'         => $a->faculty ? ['id' => $a->faculty->id, 'name' => $a->faculty->name] : null,
            'subject'         => $a->subject ? ['id' => $a->subject->id, 'code' => $a->subject->code, 'name' => $a->subject->name] : null,
            'term'            => $a->academicTerm ? ['id' => $a->academicTerm->id, 'label' => $a->academicTerm->full_label] : null,
        ];
    }
}
