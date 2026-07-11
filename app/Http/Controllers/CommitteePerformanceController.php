<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\EmployeeIPCR;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommitteePerformanceController extends Controller
{
    public function __construct(private IPCRWorkflowService $workflow)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $currentYear = IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        $query = Committee::with(['head', 'members', 'workDistributionPlans', 'subCommittees.head', 'subCommittees.members'])
            ->whereNull('parent_committee_id')
            ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY));

        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) {
            $query->where(function ($q) use ($user) {
                $q->where('head_id', $user->id)
                  ->orWhereHas('members', fn($mq) => $mq->where('users.id', $user->id))
                  ->orWhereHas('subCommittees', function ($sq) use ($user) {
                      $sq->where('head_id', $user->id)
                         ->orWhereHas('members', fn($mq) => $mq->where('users.id', $user->id));
                  });
            });
        }

        $committees = $query->orderBy('name')->get()->map(fn($c) => [
            'id'                      => $c->id,
            'name'                    => $c->name,
            'fiscal_year'             => $c->fiscal_year,
            'head_id'                 => $c->head_id,
            'head'                    => $c->head?->only('id', 'name'),
            'description'             => $c->description,
            'members'                 => $c->members->map(fn($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'pivot' => ['task' => $m->pivot->task],
            ]),
            'work_distribution_plans' => $c->workDistributionPlans->map(fn($p) => ['id' => $p->id])->values(),
            'sub_committees'          => $c->subCommittees->map(fn($sub) => [
                'id'      => $sub->id,
                'name'    => $sub->name,
                'head_id' => $sub->head_id,
                'head'    => $sub->head?->only('id', 'name'),
                'members' => $sub->members->map(fn($m) => [
                    'id'    => $m->id,
                    'name'  => $m->name,
                    'pivot' => ['task' => $m->pivot->task],
                ]),
            ])->values(),
        ]);

        return Inertia::render('PerformanceManagement/Committees/Index', [
            'committees'         => $committees,
            'users'              => User::select('id', 'name', 'position')->orderBy('name')->get(),
            'plans'              => WorkDistributionPlan::select('id', 'success_indicator', 'rated_by')
                                       ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
                                       ->orderBy('success_indicator')->get(),
            'authUser'           => $user->only('id', 'name'),
            'fiscalYears'        => IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'head_id'                       => 'nullable|exists:users,id',
            'description'                   => 'nullable|string',
            'fiscal_year'                   => 'nullable|integer|min:2000|max:2100',
            'has_subcommittees'             => 'boolean',
            'member_ids'                    => 'nullable|array',
            'member_ids.*'                  => 'exists:users,id',
            'member_tasks'                  => 'nullable|array',
            'plan_ids'                      => 'nullable|array',
            'plan_ids.*'                    => 'exists:work_distribution_plans,id',
            'sub_committees'                => 'nullable|array',
            'sub_committees.*.name'         => 'required_with:sub_committees|string|max:255',
            'sub_committees.*.head_id'      => 'nullable|exists:users,id',
            'sub_committees.*.member_ids'   => 'nullable|array',
            'sub_committees.*.member_ids.*' => 'exists:users,id',
            'sub_committees.*.member_tasks' => 'nullable|array',
        ]);

        $committee = Committee::create([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
        ]);

        $committee->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                $sub = Committee::create([
                    'name'                => $subData['name'],
                    'head_id'             => $subData['head_id'] ?? null,
                    'parent_committee_id' => $committee->id,
                ]);
                $this->syncMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
            }
        } else {
            $this->syncMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        }

        return redirect()->back()->with('success', 'Committee created.');
    }

    public function update(Request $request, Committee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'head_id'                       => 'nullable|exists:users,id',
            'description'                   => 'nullable|string',
            'fiscal_year'                   => 'nullable|integer|min:2000|max:2100',
            'has_subcommittees'             => 'boolean',
            'member_ids'                    => 'nullable|array',
            'member_ids.*'                  => 'exists:users,id',
            'member_tasks'                  => 'nullable|array',
            'plan_ids'                      => 'nullable|array',
            'plan_ids.*'                    => 'exists:work_distribution_plans,id',
            'sub_committees'                => 'nullable|array',
            'sub_committees.*.id'           => 'nullable|exists:committees,id',
            'sub_committees.*.name'         => 'required_with:sub_committees|string|max:255',
            'sub_committees.*.head_id'      => 'nullable|exists:users,id',
            'sub_committees.*.member_ids'   => 'nullable|array',
            'sub_committees.*.member_ids.*' => 'exists:users,id',
            'sub_committees.*.member_tasks' => 'nullable|array',
        ]);

        $committee->update([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
        ]);

        $committee->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                if (!empty($subData['id'])) {
                    $sub = Committee::find($subData['id']);
                    if ($sub && $sub->parent_committee_id === $committee->id) {
                        $sub->update(['name' => $subData['name'], 'head_id' => $subData['head_id'] ?? null]);
                        $this->syncMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
                    }
                } else {
                    $sub = Committee::create([
                        'name'                => $subData['name'],
                        'head_id'             => $subData['head_id'] ?? null,
                        'parent_committee_id' => $committee->id,
                    ]);
                    $this->syncMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
                }
            }
        } else {
            $this->syncMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        }

        return redirect()->back()->with('success', 'Committee updated.');
    }

    public function destroy(Committee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $committee->delete();
        return redirect()->back()->with('success', 'Committee deleted.');
    }

    public function show(Request $request, Committee $committee)
    {
        $user = auth()->user();
        $committee->load(['head', 'members', 'workDistributionPlans.performance_indicator', 'subCommittees.head', 'subCommittees.members']);

        $isHead    = $user->id == $committee->head_id;
        $isSubHead = $committee->subCommittees->contains(fn ($sub) => $sub->head_id == $user->id);
        $canManage = $user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR']) || $isHead;
        $isMember  = $committee->members->contains('id', $user->id)
            || $isSubHead
            || $committee->subCommittees->contains(fn ($sub) => $sub->members->contains('id', $user->id));

        if (!$canManage && !$isMember) {
            abort(403, 'You are not a member of this committee.');
        }

        // For each plan, list members; each member has all their IPCR periods (latest first)
        $planMemberData = $committee->workDistributionPlans->map(function ($plan) use ($committee) {
            $members = $committee->members->map(function ($member) use ($plan) {
                $ipcrs = EmployeeIPCR::where('user_id', $member->id)
                    ->whereHas('plans', fn($q) => $q->where('work_distribution_plans.id', $plan->id))
                    ->with(['plans' => fn($q) => $q->where('work_distribution_plans.id', $plan->id)])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $periods = $ipcrs->map(function ($ipcr) {
                    $pivot = $ipcr->plans->first()?->pivot;
                    return [
                        'ipcr_id'        => $ipcr->id,
                        'rating_period'  => $ipcr->rating_period ?? '—',
                        'ipcr_status'    => $ipcr->status,
                        'can_rate'       => $ipcr->status === 'Submitted for Rating',
                        'accomplishment' => $pivot?->accomplishment,
                        'mov_link'       => $pivot?->mov_link,
                        'sup_quality'    => $pivot?->sup_quality,
                        'sup_efficiency' => $pivot?->sup_efficiency,
                        'sup_timeliness' => $pivot?->sup_timeliness,
                        'sup_average'    => $pivot?->sup_average,
                    ];
                })->values();

                return [
                    'user_id'       => $member->id,
                    'user_name'     => $member->name,
                    'user_position' => $member->position,
                    'task'          => $member->pivot->task,
                    'periods'       => $periods,
                ];
            });

            return [
                'plan'    => $plan,
                'members' => $members,
            ];
        });

        // ── Task board (this committee + its sub-committees) ────────────
        $currentPeriod = IPCRRatingPeriod::current()->first();
        $periodId      = (int) $request->input('rating_period_id', $currentPeriod?->id) ?: null;

        $boardCommitteeIds = collect([$committee->id])->merge($committee->subCommittees->pluck('id'));

        $tasks = \App\Models\CommitteeTask::with(['assignees:id,name', 'plan:id,success_indicator', 'period:id,label', 'committee:id,name,parent_committee_id', 'updates.user:id,name'])
            ->withCount('updates')
            ->whereIn('committee_id', $boardCommitteeIds)
            ->forPeriod($periodId)
            ->orderBy('sort_order')
            ->get();

        $boardMembers = $committee->members->map(fn ($m) => $m->only('id', 'name'))
            ->concat($committee->subCommittees->flatMap(fn ($sub) => $sub->members->map(fn ($m) => $m->only('id', 'name'))))
            ->concat(collect([$committee->head?->only('id', 'name')])->filter())
            ->unique('id')
            ->values();

        return Inertia::render('PerformanceManagement/Committees/Show', [
            'committee'      => $committee,
            'planMemberData' => $planMemberData,
            'authUser'       => $user->only('id', 'name'),
            'isHead'         => $isHead,
            'canManage'      => $canManage,
            'tasks'            => $tasks,
            'boardMembers'     => $boardMembers,
            'ratingPeriods'    => IPCRRatingPeriod::orderByDesc('year')->orderByDesc('semester')->get(['id', 'label', 'status', 'is_current']),
            'selectedPeriodId' => $periodId,
            'canManageBoard'   => app(\App\Services\CommitteeBoardService::class)->canManageBoard($user, $committee) || $isSubHead,
        ]);
    }

    /**
     * Member saves their own accomplishment for a specific IPCR + plan.
     */
    public function saveMemberAccomplishment(Request $request, Committee $committee, User $member)
    {
        $user = auth()->user();
        if ($user->id !== $member->id) {
            abort(403, 'You can only update your own accomplishment.');
        }

        $request->validate([
            'ipcr_id'        => 'required|exists:employee_ipcrs,id',
            'plan_id'        => 'required|exists:work_distribution_plans,id',
            'accomplishment' => 'nullable|string|max:500',
            'mov_link'       => 'nullable|url|max:255',
        ]);

        $ipcr = EmployeeIPCR::where('id', $request->ipcr_id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->workflow->assertMutable($ipcr);

        if (!$ipcr->plans()->where('work_distribution_plans.id', $request->plan_id)->exists()) {
            abort(404, 'This plan is not in the specified IPCR.');
        }

        $ipcr->plans()->updateExistingPivot($request->plan_id, [
            'accomplishment' => $request->accomplishment,
            'mov_link'       => $request->mov_link,
        ]);

        return redirect()->back()->with('success', 'Accomplishment saved.');
    }

    /**
     * Head rates a member for a specific IPCR + plan.
     * Locked when IPCR status is not "Submitted for Rating".
     */
    public function rateMember(Request $request, Committee $committee, User $member)
    {
        $user   = auth()->user();
        $isHead = $user->id == $committee->head_id;

        // Sub-committee head may rate members of their OWN sub-committee
        // (mirrors the FL hierarchical gate in rateAssignment)
        $isSubHeadForMember = Committee::where('parent_committee_id', $committee->id)
            ->where('head_id', $user->id)
            ->whereHas('members', fn ($q) => $q->where('users.id', $member->id))
            ->exists();

        $canRate = $isHead || $isSubHeadForMember || $user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD']);
        if (!$canRate) {
            abort(403, 'Only the committee head or the member\'s sub-committee head can rate members.');
        }

        $request->validate([
            'ipcr_id'        => 'required|exists:employee_ipcrs,id',
            'plan_id'        => 'required|exists:work_distribution_plans,id',
            'accomplishment' => 'nullable|string|max:500',
            'mov_link'       => 'nullable|url|max:255',
            'sup_quality'    => 'nullable|integer|min:1|max:5',
            'sup_efficiency' => 'nullable|integer|min:1|max:5',
            'sup_timeliness' => 'nullable|integer|min:1|max:5',
        ]);

        $ipcr = EmployeeIPCR::where('id', $request->ipcr_id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->workflow->assertMutable($ipcr);

        if ($ipcr->status !== 'Submitted for Rating') {
            abort(403, 'Ratings can only be edited while the IPCR is "Submitted for Rating".');
        }

        if (!$ipcr->plans()->where('work_distribution_plans.id', $request->plan_id)->exists()) {
            abort(404, 'This plan is not in the specified IPCR.');
        }

        $ratings = collect([
            $request->sup_quality,
            $request->sup_efficiency,
            $request->sup_timeliness,
        ])->filter(fn($v) => !is_null($v));

        $supAverage = $ratings->count() ? round($ratings->avg(), 2) : null;

        $ipcr->plans()->updateExistingPivot($request->plan_id, [
            'accomplishment' => $request->accomplishment,
            'mov_link'       => $request->mov_link,
            'sup_quality'    => $request->sup_quality,
            'sup_efficiency' => $request->sup_efficiency,
            'sup_timeliness' => $request->sup_timeliness,
            'sup_average'    => $supAverage,
        ]);

        return redirect()->back()->with('success', 'Rating saved.');
    }

    private function syncMembers(Committee $committee, array $memberIds, array $memberTasks): void
    {
        $syncData = [];
        foreach ($memberIds as $userId) {
            $syncData[$userId] = ['task' => $memberTasks[$userId] ?? null];
        }
        $committee->members()->sync($syncData);
    }
}
