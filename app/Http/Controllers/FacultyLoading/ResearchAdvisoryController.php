<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
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
            'school_year_id'   => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'student_name'     => 'required|string|max:200',
            'student_id'       => 'nullable|integer',
            'research_title'   => 'required|string|max:500',
            'grade_level'      => 'required|integer|min:7|max:12',
            'advisory_type'    => ['required', Rule::in(['thesis', 'investigatory', 'science_research', 'feasibility'])],
            'load_units'       => 'required|numeric|min:0.5|max:5',
            'remarks'          => 'nullable|string|max:500',
        ]);

        $existingLoad = FacultyLoad::where('user_id', $data['user_id'])
            ->where('academic_term_id', $data['academic_term_id'])
            ->first();
        if ($existingLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        // Check the 5-unit research cap for this faculty + term
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

        // Ensure FacultyLoad exists; create a linked LoadAssignment
        $load       = $this->loads->findOrCreateFacultyLoad($data['user_id'], $data['school_year_id'], $data['academic_term_id']);
        $assignment = LoadAssignment::create([
            'faculty_load_id'  => $load->id,
            'user_id'          => $data['user_id'],
            'school_year_id'   => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'assignment_type'  => 'research',
            'load_units'       => $data['load_units'],
            'description'      => "Research advisory: {$data['student_name']}",
            'created_by'       => Auth::id(),
        ]);

        ResearchAdvisory::create(array_merge($data, [
            'load_assignment_id' => $assignment->id,
            'status'             => 'active',
        ]));

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
            'advisory_type'  => ['required', Rule::in(['thesis', 'investigatory', 'science_research', 'feasibility'])],
            'load_units'     => 'required|numeric|min:0.5|max:5',
            'status'         => ['nullable', Rule::in(['active', 'inactive', 'completed'])],
            'remarks'        => 'nullable|string|max:500',
        ]);

        // Re-check cap excluding this record
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

        $researchAdvisory->update($data);

        // Sync the linked LoadAssignment if present
        if ($researchAdvisory->load_assignment_id) {
            LoadAssignment::where('id', $researchAdvisory->load_assignment_id)
                ->update(['load_units' => $data['load_units']]);
        }

        if ($researchAdvisory->academicTerm && $load) {
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

        $userId  = $researchAdvisory->user_id;
        $termId  = $researchAdvisory->academic_term_id;
        $laId    = $researchAdvisory->load_assignment_id;

        $researchAdvisory->delete();

        if ($laId) {
            LoadAssignment::destroy($laId);
        }

        $load = FacultyLoad::where('user_id', $userId)->where('academic_term_id', $termId)->first();
        if ($load) {
            $this->loads->syncLoad($load);
        }

        return back()->with('success', 'Research advisory removed.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function mapAdvisory(ResearchAdvisory $r): array
    {
        return [
            'id'             => $r->id,
            'student_name'   => $r->student_name,
            'student_id'     => $r->student_id,
            'research_title' => $r->research_title,
            'grade_level'    => $r->grade_level,
            'advisory_type'  => $r->advisory_type,
            'load_units'     => (float) $r->load_units,
            'status'         => $r->status,
            'remarks'        => $r->remarks,
            'faculty'        => $r->faculty ? ['id' => $r->faculty->id, 'name' => $r->faculty->name] : null,
            'term'           => $r->academicTerm ? ['id' => $r->academicTerm->id, 'label' => $r->academicTerm->full_label] : null,
        ];
    }
}
