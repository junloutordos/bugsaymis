<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResearchAdvisoryController extends Controller
{
    public function __construct(private readonly LoadComputationService $loads) {}

    // ── List research advisories ──────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);
        $facultyId   = $request->input('faculty_id');

        $advisories = ResearchAdvisory::with(['faculty:id,name', 'academicTerm.schoolYear'])
            ->when($termId,    fn ($q) => $q->where('academic_term_id', $termId))
            ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId))
            ->orderBy('user_id')
            ->get()
            ->map(fn ($r) => $this->mapAdvisory($r));

        $terms   = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);
        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))->orderBy('name')->get(['id', 'name', 'position']);

        return Inertia::render('FacultyLoading/ResearchAdvisories/Index', [
            'advisories'  => $advisories,
            'terms'       => $terms,
            'faculty'     => $faculty,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'faculty_id']),
        ]);
    }

    // ── Create a research advisory ────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'student_name'     => 'required|string|max:200',
            'student_id'       => 'nullable|integer',
            'research_title'   => 'required|string|max:500',
            'grade_level'      => 'required|integer|min:7|max:12',
            'advisory_role'    => ['required', Rule::in(['lead', 'co_adviser'])],
            'research_type'    => ['nullable', Rule::in(['thesis', 'investigatory', 'science_research', 'feasibility'])],
            'load_units'       => 'required|numeric|min:0.5|max:5',
            'remarks'          => 'nullable|string|max:500',
        ]);

        // Derive school_year_id from the selected term
        $term         = AcademicTerm::findOrFail($data['academic_term_id']);
        $schoolYearId = $term->school_year_id;

        $existingLoad = FacultyLoad::where('user_id', $data['user_id'])
            ->where('academic_term_id', $data['academic_term_id'])
            ->first();
        if ($existingLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        // Cap check — per-student advisory rows only; designation-sourced units are now RA-owned
        $currentResearchUnits = ResearchAdvisory::where('user_id', $data['user_id'])
            ->where('academic_term_id', $data['academic_term_id'])
            ->where('status', 'active')
            ->sum('load_units');

        if (($currentResearchUnits + $data['load_units']) > LoadComputationService::MAX_RESEARCH_UNITS) {
            $remaining = max(0, LoadComputationService::MAX_RESEARCH_UNITS - $currentResearchUnits);
            return back()->withErrors([
                'load_units' => "Adding {$data['load_units']} units would exceed the maximum research advisory load of "
                              . LoadComputationService::MAX_RESEARCH_UNITS . " units. "
                              . "Remaining capacity: {$remaining} units.",
            ]);
        }

        $load = $this->loads->findOrCreateFacultyLoad($data['user_id'], $schoolYearId, $data['academic_term_id']);

        // Create advisory first so recomputeGradeAssignment can sum it from the DB
        $advisory = ResearchAdvisory::create(array_merge($data, [
            'school_year_id'     => $schoolYearId,
            'load_assignment_id' => null,
            'status'             => 'active',
        ]));

        $gradeLa = $this->recomputeGradeAssignment($data['user_id'], $data['academic_term_id'], $data['grade_level'], $load);
        if ($gradeLa) {
            $advisory->update(['load_assignment_id' => $gradeLa->id]);
        }

        $this->loads->syncLoad($load);

        return back()->with('success', 'Research advisory added.');
    }

    // ── Update a research advisory ────────────────────────────────────────────

    public function update(Request $request, ResearchAdvisory $researchAdvisory): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $load = FacultyLoad::where('user_id', $researchAdvisory->user_id)
            ->where('academic_term_id', $researchAdvisory->academic_term_id)
            ->first();
        if ($load?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        $data = $request->validate([
            'student_name'   => 'required|string|max:200',
            'student_id'     => 'nullable|integer',
            'research_title' => 'required|string|max:500',
            'grade_level'    => 'required|integer|min:7|max:12',
            'advisory_role'  => ['required', Rule::in(['lead', 'co_adviser'])],
            'research_type'  => ['nullable', Rule::in(['thesis', 'investigatory', 'science_research', 'feasibility'])],
            'load_units'     => 'required|numeric|min:0.5|max:5',
            'status'         => ['nullable', Rule::in(['active', 'completed', 'dropped'])],
            'remarks'        => 'nullable|string|max:500',
        ]);

        // Cap check excluding this record
        $currentResearchUnits = ResearchAdvisory::where('user_id', $researchAdvisory->user_id)
            ->where('academic_term_id', $researchAdvisory->academic_term_id)
            ->where('status', 'active')
            ->where('id', '!=', $researchAdvisory->id)
            ->sum('load_units');

        if (($currentResearchUnits + $data['load_units']) > LoadComputationService::MAX_RESEARCH_UNITS) {
            $remaining = max(0, LoadComputationService::MAX_RESEARCH_UNITS - $currentResearchUnits);
            return back()->withErrors([
                'load_units' => "Research advisory load would exceed the maximum of "
                              . LoadComputationService::MAX_RESEARCH_UNITS . " units. "
                              . "Available: {$remaining} units.",
            ]);
        }

        $oldGradeLevel = $researchAdvisory->grade_level;
        $researchAdvisory->update($data);

        // Recompute grade-level LoadAssignment(s); handle grade_level changes
        if ($oldGradeLevel !== (int) $data['grade_level']) {
            $this->recomputeGradeAssignment($researchAdvisory->user_id, $researchAdvisory->academic_term_id, $oldGradeLevel, $load);
        }
        $newLa = $this->recomputeGradeAssignment($researchAdvisory->user_id, $researchAdvisory->academic_term_id, (int) $data['grade_level'], $load);
        if ($newLa && $researchAdvisory->load_assignment_id !== $newLa->id) {
            $researchAdvisory->update(['load_assignment_id' => $newLa->id]);
        }

        if ($load) {
            $this->loads->syncLoad($load);
        }

        return back()->with('success', 'Research advisory updated.');
    }

    // ── Remove a research advisory ────────────────────────────────────────────

    public function destroy(ResearchAdvisory $researchAdvisory): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $load = FacultyLoad::where('user_id', $researchAdvisory->user_id)
            ->where('academic_term_id', $researchAdvisory->academic_term_id)
            ->first();
        if ($load?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        $userId     = $researchAdvisory->user_id;
        $termId     = $researchAdvisory->academic_term_id;
        $gradeLevel = $researchAdvisory->grade_level;

        $researchAdvisory->delete();

        if ($load) {
            // Recompute (or delete) the shared grade-level LoadAssignment
            $this->recomputeGradeAssignment($userId, $termId, $gradeLevel, $load);
            $this->loads->syncLoad($load);
        }

        return back()->with('success', 'Research advisory removed.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Find or create the shared grade-level LoadAssignment (RES-GX) for a faculty member,
     * recomputing its units from the sum of all active advisories for that grade.
     * Deletes the LoadAssignment if no active advisories remain.
     */
    private function recomputeGradeAssignment(int $userId, int $termId, int $gradeLevel, FacultyLoad $load): ?LoadAssignment
    {
        $designation = Designation::where('code', "RES-G{$gradeLevel}")->first();
        if (! $designation) {
            return null;
        }

        $total = (float) ResearchAdvisory::where('user_id', $userId)
            ->where('academic_term_id', $termId)
            ->where('grade_level', $gradeLevel)
            ->where('status', 'active')
            ->sum('load_units');

        $la = LoadAssignment::where('faculty_load_id', $load->id)
            ->where('designation_id', $designation->id)
            ->first();

        if ($total <= 0) {
            $la?->delete();
            return null;
        }

        if ($la) {
            $la->update(['load_units' => $total]);
        } else {
            $la = LoadAssignment::create([
                'faculty_load_id'  => $load->id,
                'user_id'          => $userId,
                'school_year_id'   => $load->school_year_id,
                'academic_term_id' => $termId,
                'designation_id'   => $designation->id,
                'assignment_type'  => 'research',
                'load_units'       => $total,
                'description'      => "Grade {$gradeLevel} Research Advisory",
                'is_overridden'    => true,
                'created_by'       => Auth::id(),
            ]);
        }

        return $la;
    }

    private function mapAdvisory(ResearchAdvisory $r): array
    {
        return [
            'id'            => $r->id,
            'student_name'  => $r->student_name,
            'student_id'    => $r->student_id,
            'research_title'=> $r->research_title,
            'grade_level'   => $r->grade_level,
            'advisory_role' => $r->advisory_role,
            'research_type' => $r->research_type,
            'load_units'    => (float) $r->load_units,
            'status'        => $r->status,
            'remarks'       => $r->remarks,
            'faculty'       => $r->faculty ? ['id' => $r->faculty->id, 'name' => $r->faculty->name] : null,
            'term'          => $r->academicTerm ? ['id' => $r->academicTerm->id, 'label' => $r->academicTerm->full_label] : null,
        ];
    }
}
