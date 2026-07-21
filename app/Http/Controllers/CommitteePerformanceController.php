<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\FacultyLoading\CommitteeRatingService;
use App\Services\FacultyLoading\CommitteeRosterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommitteePerformanceController extends Controller
{
    public function __construct(
        private CommitteeRosterService $roster,
        private CommitteeRatingService $rating,
    ) {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $currentYear = IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = (int) $request->query('term_id', $currentTerm?->id) ?: null;

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

        $committeesRaw = $query->orderBy('name')->get();

        // Batch-fetch this term's active assignment counts (roster is now
        // term-scoped via faculty_committee_assignments) to avoid N+1.
        $allIds = $committeesRaw->flatMap(fn ($c) => collect([$c->id])->merge($c->subCommittees->pluck('id')));
        $assignmentCounts = $termId
            ? FacultyCommitteeAssignment::whereIn('committee_id', $allIds)
                ->where('academic_term_id', $termId)
                ->where('status', 'active')
                ->selectRaw('committee_id, COUNT(*) as cnt')
                ->groupBy('committee_id')
                ->pluck('cnt', 'committee_id')
            : collect();

        $committees = $committeesRaw->map(fn($c) => [
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
            'active_assignment_count' => $assignmentCounts->get($c->id, 0),
            'work_distribution_plans' => $c->workDistributionPlans->map(fn($p) => ['id' => $p->id])->values(),
            'sub_committees'          => $c->subCommittees->map(fn($sub) => [
                'id'                      => $sub->id,
                'name'                    => $sub->name,
                'head_id'                 => $sub->head_id,
                'head'                    => $sub->head?->only('id', 'name'),
                'active_assignment_count' => $assignmentCounts->get($sub->id, 0),
                'members' => $sub->members->map(fn($m) => [
                    'id'    => $m->id,
                    'name'  => $m->name,
                    'pivot' => ['task' => $m->pivot->task],
                ]),
            ])->values(),
        ]);

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        return Inertia::render('PerformanceManagement/Committees/Index', [
            'committees'         => $committees,
            'users'              => User::employees()->select('id', 'name', 'position')->orderBy('name')->get(),
            'plans'              => WorkDistributionPlan::select('id', 'success_indicator', 'rated_by')
                                       ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
                                       ->orderBy('success_indicator')->get(),
            'authUser'           => $user->only('id', 'name'),
            'fiscalYears'        => IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
            'terms'              => $terms,
            'selectedTermId'     => $termId,
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

        $this->roster->reconcileCurrentTerm($committee);

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

        $this->roster->reconcileCurrentTerm($committee);

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
        $committee->load(['head', 'workDistributionPlans.performance_indicator', 'subCommittees.head']);

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = (int) $request->input('term_id', $currentTerm?->id) ?: null;
        $terms       = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        // Roster is now term-scoped via faculty_committee_assignments — the
        // same source Faculty Loading reads/writes — instead of the DM
        // catalog's committee_user pivot, so both modules always agree on
        // who's actually serving this term.
        $committeeIds    = collect([$committee->id])->merge($committee->subCommittees->pluck('id'));
        $allAssignments  = FacultyCommitteeAssignment::with('faculty:id,name,position')
            ->whereIn('committee_id', $committeeIds)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->get();
        $assignments = $allAssignments->where('committee_id', $committee->id)->values();

        $isHead    = $user->id == $committee->head_id;
        $isSubHead = $committee->subCommittees->contains(fn ($sub) => $sub->head_id == $user->id);
        $canManage = $user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR']) || $isHead;
        $isMember  = $allAssignments->contains('user_id', $user->id) || $isHead || $isSubHead;

        if (!$canManage && !$isMember) {
            abort(403, 'You are not a member of this committee.');
        }

        // For each plan, list members; each member has all their IPCR periods (latest first)
        $planMemberData = $committee->workDistributionPlans->map(function ($plan) use ($assignments) {
            $members = $assignments->map(function ($a) use ($plan) {
                $member = $a->faculty;
                $ipcrs = EmployeeIPCR::where('user_id', $member->id)
                    ->whereHas('plans', fn($q) => $q->where('work_distribution_plans.id', $plan->id))
                    ->with(['plans' => fn($q) => $q->where('work_distribution_plans.id', $plan->id)])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $periods = $ipcrs->map(function ($ipcr) {
                    $pivot = $ipcr->plans->first()?->pivot;
                    return [
                        'ipcr_id'           => $ipcr->id,
                        'rating_period_id'  => $ipcr->rating_period_id,
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
                    'assignment_id' => $a->id,
                    'user_id'       => $member->id,
                    'user_name'     => $member->name,
                    'user_position' => $member->position,
                    'role'          => $a->role,
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

        $tasks = \App\Models\CommitteeTask::with(['assignees:id,name', 'plan:id,success_indicator', 'period:id,label', 'committee:id,name,parent_committee_id', 'updates.user:id,name'])
            ->withCount('updates')
            ->whereIn('committee_id', $committeeIds)
            ->forPeriod($periodId)
            ->orderBy('sort_order')
            ->get();

        $boardMembers = $allAssignments->map(fn ($a) => $a->faculty->only('id', 'name'))
            ->concat(collect([$committee->head?->only('id', 'name')])->filter())
            ->unique('id')
            ->values();

        return Inertia::render('PerformanceManagement/Committees/Show', [
            'committee'      => $committee,
            'activeMemberCount' => $assignments->count(),
            'planMemberData' => $planMemberData,
            'authUser'       => $user->only('id', 'name'),
            'isHead'         => $isHead,
            'canManage'      => $canManage,
            'tasks'            => $tasks,
            'boardMembers'     => $boardMembers,
            'ratingPeriods'    => IPCRRatingPeriod::orderByDesc('year')->orderByDesc('semester')->get(['id', 'label', 'status', 'is_current']),
            'selectedPeriodId' => $periodId,
            'terms'            => $terms,
            'selectedTermId'   => $termId,
            'canManageBoard'   => app(\App\Services\CommitteeBoardService::class)->canManageBoard($user, $committee) || $isSubHead,
        ]);
    }

    /**
     * Member saves their own accomplishment for a specific plan/period.
     */
    public function saveMemberAccomplishment(Request $request, Committee $committee, User $member)
    {
        $user = auth()->user();
        if ($user->id !== $member->id) {
            abort(403, 'You can only update your own accomplishment.');
        }

        $data = $request->validate([
            'ipcr_id'                   => 'nullable|exists:employee_ipcrs,id',
            'work_distribution_plan_id' => 'required|exists:work_distribution_plans,id',
            'rating_period_id'          => 'nullable|exists:ipcr_rating_periods,id',
            'term_id'                   => 'nullable|exists:academic_terms,id',
            'accomplishment'            => 'nullable|string|max:1000',
            'mov_link'                  => 'nullable|string|max:500',
        ]);

        $assignment = $this->resolveAssignment($committee, $member, $data['term_id'] ?? null);

        $this->rating->saveAccomplishment($assignment, $data);

        return redirect()->back()->with('success', 'Accomplishment saved.');
    }

    /**
     * Head rates a member for a specific plan/period.
     * Locked when IPCR status is not "Submitted for Rating".
     */
    public function rateMember(Request $request, Committee $committee, User $member)
    {
        $user   = auth()->user();
        $isHead = $user->id == $committee->head_id;
        $termId = (int) ($request->input('term_id') ?: AcademicTerm::where('is_current', true)->value('id'));

        // Sub-committee head may rate members of their OWN sub-committee this
        // term (mirrors the FL hierarchical gate in rateAssignment) — checked
        // against the same term-scoped assignments used everywhere else,
        // not the DM catalog pivot, so this gate can't drift from the roster.
        $isSubHeadForMember = FacultyCommitteeAssignment::where('academic_term_id', $termId)
            ->where('status', 'active')
            ->where('user_id', $member->id)
            ->whereIn('committee_id', Committee::where('parent_committee_id', $committee->id)->where('head_id', $user->id)->pluck('id'))
            ->exists();

        $canRate = $isHead || $isSubHeadForMember || $user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD']);
        if (!$canRate) {
            abort(403, 'Only the committee head or the member\'s sub-committee head can rate members.');
        }

        $data = $request->validate([
            'ipcr_id'                   => 'nullable|exists:employee_ipcrs,id',
            'work_distribution_plan_id' => 'required|exists:work_distribution_plans,id',
            'rating_period_id'          => 'nullable|exists:ipcr_rating_periods,id',
            'term_id'                   => 'nullable|exists:academic_terms,id',
            'accomplishment'            => 'nullable|string|max:1000',
            'mov_link'                  => 'nullable|string|max:500',
            'sup_quality'               => 'nullable|numeric|min:1|max:5',
            'sup_efficiency'            => 'nullable|numeric|min:1|max:5',
            'sup_timeliness'            => 'nullable|numeric|min:1|max:5',
        ]);

        $assignment = $this->resolveAssignment($committee, $member, $termId);

        $this->rating->rate($assignment, $data);

        return redirect()->back()->with('success', 'Rating saved.');
    }

    /** Resolve the member's active committee assignment for a term (default: current). */
    private function resolveAssignment(Committee $committee, User $member, ?int $termId): FacultyCommitteeAssignment
    {
        $termId ??= AcademicTerm::where('is_current', true)->value('id');

        $assignment = FacultyCommitteeAssignment::where('committee_id', $committee->id)
            ->where('user_id', $member->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->first();

        abort_unless($assignment, 404, 'This member is not an active assignee of this committee for the selected term.');

        return $assignment;
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
