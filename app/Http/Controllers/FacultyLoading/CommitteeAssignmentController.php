<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Committee;
use App\Models\EmployeeIPCR;
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

        $assignments = FacultyCommitteeAssignment::with(['faculty:id,name', 'committee:id,name,code,parent_committee_id', 'academicTerm.schoolYear'])
            ->when($termId,    fn ($q) => $q->where('academic_term_id', $termId))
            ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId))
            ->orderBy('user_id')
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->orderBy('name')->get(['id', 'name', 'position']);

        $committees = Committee::active()
            ->with(['workDistributionPlans:id', 'subCommittees:id,name,code,chairperson_load_units,member_load_units,parent_committee_id,is_active'])
            ->whereNull('parent_committee_id')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'committee_type', 'chairperson_load_units', 'member_load_units', 'parent_committee_id'])
            ->map(fn ($c) => [
                'id'                     => $c->id,
                'name'                   => $c->name,
                'code'                   => $c->code,
                'committee_type'         => $c->committee_type,
                'chairperson_load_units' => (float) $c->chairperson_load_units,
                'member_load_units'      => (float) $c->member_load_units,
                'plan_ids'               => $c->workDistributionPlans->pluck('id')->toArray(),
                'sub_committees'         => $c->subCommittees
                    ->where('is_active', true)
                    ->map(fn ($s) => [
                        'id'                     => $s->id,
                        'name'                   => $s->name,
                        'code'                   => $s->code,
                        'chairperson_load_units' => (float) $s->chairperson_load_units,
                        'member_load_units'      => (float) $s->member_load_units,
                    ])->values(),
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
        // Admins see everything; chairpersons and members see their own
        // committee (or its parent/subs). Everyone else is blocked below,
        // after membership is resolved.
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        // Rating period scope (accomplishments + ratings are per semestral period)
        $currentPeriod = \App\Models\IPCRRatingPeriod::current()->first();
        $periodId      = (int) $request->input('rating_period_id', $currentPeriod?->id) ?: null;
        $ratingPeriods = \App\Models\IPCRRatingPeriod::orderByDesc('year')->orderByDesc('semester')
            ->get(['id', 'label', 'status', 'is_current']);

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $committee->load(['head:id,name,position', 'workDistributionPlans:id,success_indicator,rated_by']);

        $assignments = FacultyCommitteeAssignment::with([
                'faculty:id,name,position',
                'accomplishments' => fn ($q) => $q->where(fn ($qq) =>
                    $qq->whereNull('rating_period_id')
                       ->when($periodId, fn ($qqq) => $qqq->orWhere('rating_period_id', $periodId))
                ),
            ])
            ->where('committee_id', $committee->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->get();

        $authUser  = auth()->user();
        $canManage = $authUser->hasPermission('faculty_loading.manage');

        // Own assignments for this committee
        $isChairperson = $assignments
            ->where('user_id', $authUser->id)
            ->whereIn('role', ['chairperson', 'co_chair'])
            ->isNotEmpty();

        // Main committee chairperson can also view/rate their sub-committees
        if (! $isChairperson && $committee->parent_committee_id) {
            $isChairperson = FacultyCommitteeAssignment::where('committee_id', $committee->parent_committee_id)
                ->where('academic_term_id', $termId)
                ->where('user_id', $authUser->id)
                ->whereIn('role', ['chairperson', 'co_chair'])
                ->where('status', 'active')
                ->exists();
        }

        // Membership gate (route is open to view_own holders): admins,
        // chairpersons, this committee's assignees, and heads always pass.
        $isMember = $assignments->contains('user_id', $authUser->id)
            || $committee->head_id === $authUser->id
            || FacultyCommitteeAssignment::whereIn('committee_id', array_filter([$committee->id, $committee->parent_committee_id]))
                ->where('user_id', $authUser->id)
                ->where('status', 'active')
                ->exists();

        abort_unless($canManage || $isChairperson || $isMember, 403, 'You are not a member of this committee.');

        $planMemberData = $committee->workDistributionPlans->map(function ($plan) use ($assignments, $periodId) {
            $members = $assignments->map(function ($a) use ($plan, $periodId) {
                // Prefer the selected period's row; fall back to the legacy NULL-period row
                $planAccs = $a->accomplishments->where('work_distribution_plan_id', $plan->id);
                $acc = ($periodId ? $planAccs->firstWhere('rating_period_id', $periodId) : null)
                    ?? $planAccs->firstWhere('rating_period_id', null);
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
            'ratingPeriods'    => $ratingPeriods,
            'selectedPeriodId' => $periodId,
            'tasks'            => \App\Models\CommitteeTask::with(['assignees:id,name', 'plan:id,success_indicator', 'period:id,label', 'updates.user:id,name'])
                                    ->withCount('updates')
                                    ->where('committee_id', $committee->id)
                                    ->forPeriod($periodId)
                                    ->orderBy('sort_order')
                                    ->get(),
            'boardMembers'     => $assignments->map(fn ($a) => $a->faculty->only('id', 'name'))->unique('id')->values(),
            'canManageBoard'   => app(\App\Services\CommitteeBoardService::class)->canManageBoard($authUser, \App\Models\Committee::find($committee->id)),
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

        $existingLoad = FacultyLoad::where('user_id', $data['user_id'])
            ->where('academic_term_id', $data['academic_term_id'])
            ->first();
        if ($existingLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
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

        $load       = $this->loads->findOrCreateFacultyLoad($data['user_id'], $data['school_year_id'], $data['academic_term_id']);
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

        $load = FacultyLoad::where('user_id', $committeeAssignment->user_id)
            ->where('academic_term_id', $committeeAssignment->academic_term_id)
            ->first();
        if ($load?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

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

        $load = FacultyLoad::where('user_id', $committeeAssignment->user_id)
            ->where('academic_term_id', $committeeAssignment->academic_term_id)
            ->first();
        if ($load?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

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
            'rating_period_id'          => 'nullable|exists:ipcr_rating_periods,id',
            'accomplishment'            => 'nullable|string|max:1000',
            'mov_link'                  => 'nullable|string|max:500',
        ]);

        FacultyCommitteeAccomplishment::updateOrCreate(
            [
                'faculty_committee_assignment_id' => $committeeAssignment->id,
                'work_distribution_plan_id'       => $data['work_distribution_plan_id'],
                'rating_period_id'                => $data['rating_period_id'] ?? null,
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

        if (! $user->hasPermission('faculty_loading.manage')) {
            $targetCommittee   = Committee::find($committeeAssignment->committee_id);
            $isSubCommittee    = $targetCommittee && $targetCommittee->parent_committee_id !== null;
            $targetIsChairRole = in_array($committeeAssignment->role, ['chairperson', 'co_chair']);

            if ($isSubCommittee && $targetIsChairRole) {
                // Rating a sub-committee chair → must be the main committee's chairperson
                $isChairperson = FacultyCommitteeAssignment::where('committee_id', $targetCommittee->parent_committee_id)
                    ->where('academic_term_id', $committeeAssignment->academic_term_id)
                    ->where('user_id', $user->id)
                    ->whereIn('role', ['chairperson', 'co_chair'])
                    ->where('status', 'active')
                    ->exists();
            } else {
                // Rating a member of the same committee (simple, main, or sub-committee member)
                $isChairperson = FacultyCommitteeAssignment::where('committee_id', $committeeAssignment->committee_id)
                    ->where('academic_term_id', $committeeAssignment->academic_term_id)
                    ->where('user_id', $user->id)
                    ->whereIn('role', ['chairperson', 'co_chair'])
                    ->where('status', 'active')
                    ->exists();
            }

            abort_if(! $isChairperson, 403, 'Only the committee chairperson or an administrator can rate members.');
        }

        $data = $request->validate([
            'work_distribution_plan_id' => 'required|exists:work_distribution_plans,id',
            'rating_period_id'          => 'nullable|exists:ipcr_rating_periods,id',
            'accomplishment'            => 'nullable|string|max:1000',
            'mov_link'                  => 'nullable|string|max:500',
            'sup_quality'               => 'nullable|numeric|min:1|max:5',
            'sup_efficiency'            => 'nullable|numeric|min:1|max:5',
            'sup_timeliness'            => 'nullable|numeric|min:1|max:5',
        ]);

        $ratings = collect([$data['sup_quality'] ?? null, $data['sup_efficiency'] ?? null, $data['sup_timeliness'] ?? null])
            ->filter(fn ($v) => ! is_null($v));

        $supAverage = $ratings->count() ? round($ratings->avg(), 2) : null;

        FacultyCommitteeAccomplishment::updateOrCreate(
            [
                'faculty_committee_assignment_id' => $committeeAssignment->id,
                'work_distribution_plan_id'       => $data['work_distribution_plan_id'],
                'rating_period_id'                => $data['rating_period_id'] ?? null,
            ],
            [
                'accomplishment' => $data['accomplishment'] ?? null,
                'mov_link'       => $data['mov_link'] ?? null,
                'sup_quality'    => $data['sup_quality'] ?? null,
                'sup_efficiency' => $data['sup_efficiency'] ?? null,
                'sup_timeliness' => $data['sup_timeliness'] ?? null,
                'sup_average'    => $supAverage,
            ]
        );

        // Mirror the supervisor rating into the teacher's IPCR plan pivot so the
        // chairperson only needs to rate once (in Faculty Loading) and it flows
        // into IPCR. Target the IPCR of the SAME rating period when one is
        // selected; fall back to the latest rateable IPCR for legacy rows.
        $activeIpcr = EmployeeIPCR::where('user_id', $committeeAssignment->user_id)
            ->whereIn('status', ['Targets Approved', 'Submitted for Rating'])
            ->whereHas('plans', fn ($q) => $q->where('work_distribution_plans.id', $data['work_distribution_plan_id']))
            ->when($data['rating_period_id'] ?? null, fn ($q, $pid) => $q->where('rating_period_id', $pid))
            ->latest()
            ->first();

        if ($activeIpcr) {
            $ipcrPivot = [
                'sup_quality'    => $data['sup_quality'] ?? null,
                'sup_efficiency' => $data['sup_efficiency'] ?? null,
                'sup_timeliness' => $data['sup_timeliness'] ?? null,
                'sup_average'    => $supAverage,
            ];
            if (! empty($data['accomplishment'])) {
                $ipcrPivot['accomplishment'] = $data['accomplishment'];
            }
            if (! empty($data['mov_link'])) {
                $ipcrPivot['mov_link'] = $data['mov_link'];
            }
            $activeIpcr->plans()->updateExistingPivot($data['work_distribution_plan_id'], $ipcrPivot);
        }

        return back()->with('success', 'Rating saved.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

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
            'committee'      => $a->committee ? ['id' => $a->committee->id, 'name' => $a->committee->name, 'code' => $a->committee->code, 'parent_committee_id' => $a->committee->parent_committee_id] : null,
            'term'           => $a->academicTerm ? ['id' => $a->academicTerm->id, 'label' => $a->academicTerm->full_label] : null,
        ];
    }
}
