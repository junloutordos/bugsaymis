<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Committee;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommitteeAssignmentController extends Controller
{
    public function __construct(private readonly LoadComputationService $loads) {}

    // ── List committee assignments ────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);
        $facultyId   = $request->input('faculty_id');

        $assignments = FacultyCommitteeAssignment::with(['faculty:id,name', 'committee:id,name,code', 'academicTerm.schoolYear'])
            ->when($termId,    fn ($q) => $q->where('academic_term_id', $termId))
            ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId))
            ->orderBy('user_id')
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));

        $terms      = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);
        $faculty    = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))->orderBy('name')->get(['id', 'name', 'position']);
        $committees = Committee::active()->orderBy('name')->get(['id', 'name', 'code', 'committee_type', 'chairperson_load_units', 'member_load_units']);

        return Inertia::render('FacultyLoading/CommitteeAssignments/Index', [
            'assignments' => $assignments,
            'terms'       => $terms,
            'faculty'     => $faculty,
            'committees'  => $committees,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'faculty_id']),
        ]);
    }

    // ── Check compliance status (JSON) ────────────────────────────────────────

    public function compliance(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'term_id'  => 'required|integer|exists:academic_terms,id',
        ]);

        $result = $this->loads->checkCommitteeCompliance($data['user_id'], $data['term_id']);

        return response()->json($result);
    }

    // ── Create a committee assignment ─────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'school_year_id'   => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'committee_id'     => 'nullable|exists:committees,id',
            'committee_name'   => 'required|string|max:200',
            'role'             => ['required', Rule::in(['chairperson', 'co_chair', 'member', 'secretary'])],
            'load_units'       => 'required|numeric|min:0|max:5',
            'remarks'          => 'nullable|string|max:500',
        ]);

        // Prevent duplicate active assignment to the same committee in the same term
        $duplicate = FacultyCommitteeAssignment::where('user_id', $data['user_id'])
            ->where('academic_term_id', $data['academic_term_id'])
            ->where('status', 'active')
            ->when(
                $data['committee_id'],
                fn ($q) => $q->where('committee_id', $data['committee_id']),
                fn ($q) => $q->where('committee_name', $data['committee_name'])
            )
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'committee_name' => 'This faculty member already has an active assignment for this committee this term.',
            ]);
        }

        // Auto-derive load units from the committee record if not overridden
        if ($data['committee_id']) {
            $committee = Committee::find($data['committee_id']);
            if ($committee && $request->missing('load_units')) {
                $data['load_units'] = $committee->loadUnitsFor($data['role']);
            }
        }

        // Ensure FacultyLoad and create linked LoadAssignment
        $load       = $this->findOrCreateLoad($data['user_id'], $data['school_year_id'], $data['academic_term_id']);
        $assignment = LoadAssignment::create([
            'faculty_load_id'  => $load->id,
            'user_id'          => $data['user_id'],
            'school_year_id'   => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'assignment_type'  => 'committee',
            'load_units'       => $data['load_units'],
            'description'      => "{$data['committee_name']} ({$data['role']})",
            'created_by'       => Auth::id(),
        ]);

        FacultyCommitteeAssignment::create(array_merge($data, [
            'load_assignment_id' => $assignment->id,
            'status'             => 'active',
        ]));

        $this->loads->syncLoad($load);

        return back()->with('success', 'Committee assignment added.');
    }

    // ── Update a committee assignment ─────────────────────────────────────────

    public function update(Request $request, FacultyCommitteeAssignment $committeeAssignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'role'       => ['required', Rule::in(['chairperson', 'co_chair', 'member', 'secretary'])],
            'load_units' => 'required|numeric|min:0|max:5',
            'status'     => ['nullable', Rule::in(['active', 'inactive'])],
            'remarks'    => 'nullable|string|max:500',
        ]);

        $committeeAssignment->update($data);

        // Keep the linked LoadAssignment in sync
        if ($committeeAssignment->load_assignment_id) {
            LoadAssignment::where('id', $committeeAssignment->load_assignment_id)
                ->update([
                    'load_units'  => $data['load_units'],
                    'description' => $committeeAssignment->committee_name . ' (' . $data['role'] . ')',
                ]);
        }

        $load = FacultyLoad::where('user_id', $committeeAssignment->user_id)
            ->where('academic_term_id', $committeeAssignment->academic_term_id)
            ->first();
        if ($load) {
            $this->loads->syncLoad($load);
        }

        return back()->with('success', 'Committee assignment updated.');
    }

    // ── Remove a committee assignment ─────────────────────────────────────────

    public function destroy(FacultyCommitteeAssignment $committeeAssignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $userId  = $committeeAssignment->user_id;
        $termId  = $committeeAssignment->academic_term_id;
        $laId    = $committeeAssignment->load_assignment_id;

        $committeeAssignment->delete();

        if ($laId) {
            LoadAssignment::destroy($laId);
        }

        $load = FacultyLoad::where('user_id', $userId)->where('academic_term_id', $termId)->first();
        if ($load) {
            $this->loads->syncLoad($load);
        }

        return back()->with('success', 'Committee assignment removed.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

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

    private function mapAssignment(FacultyCommitteeAssignment $a): array
    {
        return [
            'id'             => $a->id,
            'committee_id'   => $a->committee_id,
            'committee_name' => $a->committee_name,
            'role'           => $a->role,
            'load_units'     => (float) $a->load_units,
            'status'         => $a->status,
            'remarks'        => $a->remarks,
            'is_chairperson' => $a->isChairperson(),
            'faculty'        => $a->faculty ? ['id' => $a->faculty->id, 'name' => $a->faculty->name] : null,
            'committee'      => $a->committee ? ['id' => $a->committee->id, 'name' => $a->committee->name, 'code' => $a->committee->code] : null,
            'term'           => $a->academicTerm ? ['id' => $a->academicTerm->id, 'label' => $a->academicTerm->full_label] : null,
        ];
    }
}
