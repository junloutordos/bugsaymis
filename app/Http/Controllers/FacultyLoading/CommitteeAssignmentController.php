<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Committee;
use App\Models\FacultyLoading\FacultyCommitteeAccomplishment;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Models\WorkDistributionPlan;
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

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->orderBy('name')->get(['id', 'name', 'position']);

        $committees = Committee::active()->with('workDistributionPlans:id')->orderBy('name')
            ->get(['id', 'name', 'code', 'committee_type', 'chairperson_load_units', 'member_load_units'])
            ->map(fn ($c) => [
                'id'                     => $c->id,
                'name'                   => $c->name,
                'code'                   => $c->code,
                'committee_type'         => $c->committee_type,
                'chairperson_load_units' => (float) $c->chairperson_load_units,
                'member_load_units'      => (float) $c->member_load_units,
                'plan_ids'               => $c->workDistributionPlans->pluck('id')->toArray(),
            ]);

        $plans = WorkDistributionPlan::orderBy('success_indicator')
            ->get(['id', 'success_indicator', 'rated_by']);

        return Inertia::render('FacultyLoading/CommitteeAssignments/Index', [
            'assignments' => $assignments,
            'terms'       => $terms,
            'faculty'     => $faculty,
            'committees'  => $committees,
            'plans'       => $plans,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'faculty_id']),
        ]);
    }

    // ── Committee detail / performance view ───────────────────────────────────

    public function show(Request $request, Committee $committee): Response
    {
        $this->authorize('faculty_loading.manage');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $committee->load(['head:id,name,position', 'workDistributionPlans:id,success_indicator,rated_by']);

        $assignments = FacultyCommitteeAssignment::with([
                'faculty:id,name,position',
                'accomplishments',
            ])
            ->where('committee_id', $committee->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->get();

        $authUser      = auth()->user();
        $isChairperson = $assignments
            ->where('user_id', $authUser->id)
            ->whereIn('role', ['chairperson', 'co_chair'])
            ->isNotEmpty();
        $canManage = $authUser->hasPermission('faculty_loading.manage');

        $planMemberData = $committee->workDistributionPlans->map(function ($plan) use ($assignments) {
            $members = $assignments->map(function ($a) use ($plan) {
                $acc = $a->accomplishments->firstWhere('work_distribution_plan_id', $plan->id);
                return [
                    'assignment_id'  => $a->id,
                    'user_id'        => $a->faculty->id,
                    'user_name'      => $a->faculty->name,
                    'user_position'  => $a->faculty->position,
                    'role'           => $a->role,
                    'is_chairperson' => $a->isChairperson(),
                    'accomplishment' => $acc?->accomplishment,
                    'mov_link'       => $acc?->mov_link,
                    'sup_quality'    => $acc?->sup_quality,
                    'sup_efficiency' => $acc?->sup_efficiency,
                    'sup_timeliness' => $acc?->sup_timeliness,
                    'sup_average'    => $acc?->sup_average,
                ];
            })->values();

            return [
                'plan'    => ['id' => $plan->id, 'success_indicator' => $plan->success_indicator, 'rated_by' => $plan->rated_by],
                'members' => $members,
            ];
        });

        return Inertia::render('FacultyLoading/CommitteeAssignments/Show', [
            'committee' => [
                'id'                     => $committee->id,
                'name'                   => $committee->name,
                'code'                   => $committee->code,
                'committee_type'         => $committee->committee_type,
                'description'            => $committee->description,
                'chairperson_title'      => $committee->chairperson_title,
                'chairperson_load_units' => (float) $committee->chairperson_load_units,
                'member_load_units'      => (float) $committee->member_load_units,
                'head'                   => $committee->head?->only('id', 'name', 'position'),
            ],
            'planMemberData' => $planMemberData,
            'terms'          => $terms,
            'selectedTermId' => (int) $termId,
            'authUser'       => $authUser->only('id', 'name'),
            'isChairperson'  => $isChairperson,
            'canManage'      => $canManage,
        ]);
    }

    // ── Check compliance status (JSON) ────────────────────────────────────────

    public function compliance(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'term_id' => 'required|integer|exists:academic_terms,id',
        ]);

        return response()->json($this->loads->checkCommitteeCompliance($data['user_id'], $data['term_id']));
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
            'plan_ids'         => 'nullable|array',
            'plan_ids.*'       => 'exists:work_distribution_plans,id',
        ]);

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

        if ($data['committee_id']) {
            $committee = Committee::find($data['committee_id']);
            if ($committee) {
                if ($request->missing('load_units')) {
                    $data['load_units'] = $committee->loadUnitsFor($data['role']);
                }
                // Sync tagged WDP plans
                $committee->workDistributionPlans()->sync($data['plan_ids'] ?? []);
                // Promote to committee head when chairperson
                if (in_array($data['role'], ['chairperson', 'co_chair'])) {
                    $committee->update(['head_id' => $data['user_id']]);
                }
            }
        }

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

        FacultyCommitteeAssignment::create(array_merge(
            $data,
            ['load_assignment_id' => $assignment->id, 'status' => 'active']
        ));

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
            'plan_ids'   => 'nullable|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $committeeAssignment->update($data);

        if ($committeeAssignment->committee_id) {
            $committee = Committee::find($committeeAssignment->committee_id);
            if ($committee) {
                $committee->workDistributionPlans()->sync($data['plan_ids'] ?? []);
                if (in_array($data['role'], ['chairperson', 'co_chair'])) {
                    $committee->update(['head_id' => $committeeAssignment->user_id]);
                }
            }
        }

        if ($committeeAssignment->load_assignment_id) {
            LoadAssignment::where('id', $committeeAssignment->load_assignment_id)->update([
                'load_units'  => $data['load_units'],
                'description' => $committeeAssignment->committee_name . ' (' . $data['role'] . ')',
            ]);
        }

        $load = FacultyLoad::where('user_id', $committeeAssignment->user_id)
            ->where('academic_term_id', $committeeAssignment->academic_term_id)
            ->first();
        if ($load) $this->loads->syncLoad($load);

        return back()->with('success', 'Committee assignment updated.');
    }

    // ── Remove a committee assignment ─────────────────────────────────────────

    public function destroy(FacultyCommitteeAssignment $committeeAssignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $userId = $committeeAssignment->user_id;
        $termId = $committeeAssignment->academic_term_id;
        $laId   = $committeeAssignment->load_assignment_id;

        $committeeAssignment->delete();

        if ($laId) LoadAssignment::destroy($laId);

        $load = FacultyLoad::where('user_id', $userId)->where('academic_term_id', $termId)->first();
        if ($load) $this->loads->syncLoad($load);

        return back()->with('success', 'Committee assignment removed.');
    }

    // ── Save member accomplishment ────────────────────────────────────────────

    public function saveAccomplishment(Request $request, FacultyCommitteeAssignment $committeeAssignment): RedirectResponse
    {
        abort_if(auth()->id() !== $committeeAssignment->user_id, 403, 'You can only update your own accomplishment.');

        $data = $request->validate([
            'work_distribution_plan_id' => 'required|exists:work_distribution_plans,id',
            'accomplishment'            => 'nullable|string|max:1000',
            'mov_link'                  => 'nullable|url|max:255',
        ]);

        FacultyCommitteeAccomplishment::updateOrCreate(
            [
                'faculty_committee_assignment_id' => $committeeAssignment->id,
                'work_distribution_plan_id'       => $data['work_distribution_plan_id'],
            ],
            [
                'accomplishment' => $data['accomplishment'],
                'mov_link'       => $data['mov_link'],
            ]
        );

        return back()->with('success', 'Accomplishment saved.');
    }

    // ── Rate a member (chairperson / admin only) ──────────────────────────────

    public function rateAssignment(Request $request, FacultyCommitteeAssignment $committeeAssignment): RedirectResponse
    {
        $user = auth()->user();

        $isChairperson = FacultyCommitteeAssignment::where('committee_id', $committeeAssignment->committee_id)
            ->where('academic_term_id', $committeeAssignment->academic_term_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['chairperson', 'co_chair'])
            ->where('status', 'active')
            ->exists();

        abort_if(
            ! $isChairperson && ! $user->hasPermission('faculty_loading.manage'),
            403,
            'Only the committee chairperson or an administrator can rate members.'
        );

        $data = $request->validate([
            'work_distribution_plan_id' => 'required|exists:work_distribution_plans,id',
            'accomplishment'            => 'nullable|string|max:1000',
            'mov_link'                  => 'nullable|url|max:255',
            'sup_quality'               => 'nullable|numeric|min:1|max:5',
            'sup_efficiency'            => 'nullable|numeric|min:1|max:5',
            'sup_timeliness'            => 'nullable|numeric|min:1|max:5',
        ]);

        $ratings = collect([$data['sup_quality'] ?? null, $data['sup_efficiency'] ?? null, $data['sup_timeliness'] ?? null])
            ->filter(fn ($v) => ! is_null($v));

        FacultyCommitteeAccomplishment::updateOrCreate(
            [
                'faculty_committee_assignment_id' => $committeeAssignment->id,
                'work_distribution_plan_id'       => $data['work_distribution_plan_id'],
            ],
            [
                'accomplishment' => $data['accomplishment'] ?? null,
                'mov_link'       => $data['mov_link'] ?? null,
                'sup_quality'    => $data['sup_quality'] ?? null,
                'sup_efficiency' => $data['sup_efficiency'] ?? null,
                'sup_timeliness' => $data['sup_timeliness'] ?? null,
                'sup_average'    => $ratings->count() ? round($ratings->avg(), 2) : null,
            ]
        );

        return back()->with('success', 'Rating saved.');
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
            'faculty'        => $a->faculty ? $a->faculty->only('id', 'name') : null,
            'committee'      => $a->committee ? ['id' => $a->committee->id, 'name' => $a->committee->name, 'code' => $a->committee->code] : null,
            'term'           => $a->academicTerm ? ['id' => $a->academicTerm->id, 'label' => $a->academicTerm->full_label] : null,
        ];
    }
}
