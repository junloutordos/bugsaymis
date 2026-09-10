<?php

namespace App\Http\Controllers\PerformanceManagement;

use App\Http\Controllers\Controller;
use App\Models\Committee as GlobalCommittee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Committee;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\FacultyLoading\CommitteeRosterService;
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
    public function __construct(
        private readonly LoadComputationService $loads,
        private readonly \App\Services\PerformanceManagement\CommitteeIpcrSyncService $ipcrSync,
        private readonly \App\Services\PerformanceManagement\CommitteeIpcrRatingService $ipcrRating,
        private readonly CommitteeRosterService $roster,
        private readonly \App\Services\PerformanceManagement\CommitteeNotificationService $notifications,
        private readonly \App\Services\PerformanceManagement\CommitteePdfService $pdfExport = new \App\Services\PerformanceManagement\CommitteePdfService(),
        private readonly \App\Services\PerformanceManagement\CommitteeExcelService $excelExport = new \App\Services\PerformanceManagement\CommitteeExcelService(),
    ) {}

    // ── List committee assignments ────────────────────────────────────────────

    public function index(Request $request): Response
    {
        // Open to any authenticated user with accomplishments.view OR
        // faculty_loading.manage (route middleware) — unlike FL's own former
        // index(), this is NOT faculty_loading.manage-only: PMS's original
        // CommitteePerformanceController::index() had no authorize() call at
        // all, scoping the catalog to each viewer's own committees instead.
        // That scoped-visibility behavior is preserved below for `catalog`;
        // the campus-wide `assignments`/`faculty` admin data stays
        // manage-only, gated by $canManage rather than a hard abort so a
        // non-admin viewer still gets the rest of the page.
        $user      = auth()->user();
        $canManage = $user->hasPermission('faculty_loading.manage');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);
        $facultyId   = $request->input('faculty_id');

        $assignments = $canManage
            ? FacultyCommitteeAssignment::with(['faculty:id,name', 'committee:id,name,code,parent_committee_id', 'academicTerm.schoolYear'])
                ->when($termId,    fn ($q) => $q->where('academic_term_id', $termId))
                ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId))
                ->orderBy('user_id')
                ->get()
                ->map(fn ($a) => $this->mapAssignment($a))
            : collect();

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $faculty = $canManage
            ? User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))->orderBy('name')->get(['id', 'name', 'position'])
            : collect();

        $currentYear = \App\Models\IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        // Catalog: fully-qualified GlobalCommittee — needs forFiscalYear(),
        // which App\Models\FacultyLoading\Committee does not declare.
        $catalogQuery = GlobalCommittee::with(['head', 'members', 'workDistributionPlans', 'subCommittees.head', 'subCommittees.members', 'issuance:id,title,control_number,reference_number', 'revokedBy:id,name', 'amendedInto:id,name'])
            ->whereNull('parent_committee_id')
            ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
            ->when(! $request->boolean('show_revoked'), fn ($q) => $q->notRevoked());

        if (! $user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) {
            $catalogQuery->where(function ($q) use ($user) {
                $q->where('head_id', $user->id)
                  ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id))
                  ->orWhereHas('subCommittees', function ($sq) use ($user) {
                      $sq->where('head_id', $user->id)
                         ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
                  });
            });
        }

        $catalogRaw = $catalogQuery->orderBy('name')->get();
        $allCatalogIds = $catalogRaw->flatMap(fn ($c) => collect([$c->id])->merge($c->subCommittees->pluck('id')));
        $assignmentCounts = $termId
            ? FacultyCommitteeAssignment::whereIn('committee_id', $allCatalogIds)
                ->where('academic_term_id', $termId)->where('status', 'active')
                ->selectRaw('committee_id, COUNT(*) as cnt')->groupBy('committee_id')->pluck('cnt', 'committee_id')
            : collect();

        $mapMember = fn ($m) => [
            'id'    => $m->id,
            'name'  => $m->name,
            'pivot' => [
                'task'                => $m->pivot->task,
                'role'                => $m->pivot->role ?? 'member',
                'load_units_override' => $m->pivot->load_units_override !== null ? (float) $m->pivot->load_units_override : null,
            ],
        ];

        $catalog = $catalogRaw->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'fiscal_year' => $c->fiscal_year,
            'head_id' => $c->head_id, 'head' => $c->head?->only('id', 'name'), 'description' => $c->description,
            'max_members' => $c->max_members,
            'scope_type' => $c->scope_type, 'school_year_id' => $c->school_year_id,
            'season_starts_at' => $c->season_starts_at?->toDateString(), 'season_ends_at' => $c->season_ends_at?->toDateString(),
            'so_number' => $c->so_number, 'issuance_id' => $c->issuance_id,
            'issuance' => $c->issuance ? ['id' => $c->issuance->id, 'label' => $c->issuance->display_number ?? $c->issuance->title] : null,
            'is_revoked' => $c->isRevoked(), 'revoked_at' => $c->revoked_at?->toIso8601String(),
            'revoked_by' => $c->revokedBy?->only('id', 'name'), 'revocation_reason' => $c->revocation_reason,
            'amended_from_committee_id' => $c->amended_from_committee_id,
            'amended_into' => $c->amendedInto ? ['id' => $c->amendedInto->id, 'name' => $c->amendedInto->name] : null,
            'members' => $c->members->map($mapMember),
            'active_assignment_count' => $assignmentCounts->get($c->id, 0),
            'work_distribution_plans' => $c->workDistributionPlans->map(fn ($p) => ['id' => $p->id])->values(),
            'sub_committees' => $c->subCommittees->map(fn ($sub) => [
                'id' => $sub->id, 'name' => $sub->name, 'head_id' => $sub->head_id, 'head' => $sub->head?->only('id', 'name'),
                'active_assignment_count' => $assignmentCounts->get($sub->id, 0),
                'members' => $sub->members->map($mapMember),
            ])->values(),
        ]);

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

        return Inertia::render('PerformanceManagement/Committees/Index', [
            'assignments' => $assignments,
            'terms'       => $terms,
            'faculty'     => $faculty,
            'committees'  => $committees,
            'catalog'     => $catalog,
            'plans'       => $plans,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'faculty_id']),
            'users'       => User::employees()->select('id', 'name', 'position')->orderBy('name')->get(),
            'authUser'    => $user->only('id', 'name'),
            'canManage'   => $canManage,
            'fiscalYears' => \App\Models\IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
            'schoolYears' => \App\Models\FacultyLoading\SchoolYear::orderByDesc('id')->get(['id', 'name']),
            'showRevoked' => $request->boolean('show_revoked'),
        ]);
    }

    // ── Committee catalog CRUD (folded in from the retired CommitteePerformanceController) ──

    public function storeCommittee(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $validated = $this->validateCatalogPayload($request);

        $committee = GlobalCommittee::create([
            'name'              => $validated['name'],
            'head_id'           => $validated['head_id'] ?? null,
            'description'       => $validated['description'] ?? null,
            'fiscal_year'       => $validated['fiscal_year'] ?? null,
            'max_members'       => $validated['max_members'] ?? null,
            'scope_type'        => $validated['scope_type'] ?? GlobalCommittee::SCOPE_PERPETUAL,
            'school_year_id'    => $validated['school_year_id'] ?? null,
            'season_starts_at'  => $validated['season_starts_at'] ?? null,
            'season_ends_at'    => $validated['season_ends_at'] ?? null,
            'so_number'         => $validated['so_number'] ?? null,
            'issuance_id'       => $validated['issuance_id'] ?? null,
        ]);

        $committee->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                $sub = GlobalCommittee::create([
                    'name'                => $subData['name'],
                    'head_id'             => $subData['head_id'] ?? null,
                    'parent_committee_id' => $committee->id,
                ]);
                $this->syncCatalogMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? [], $subData['member_roles'] ?? [], $subData['member_load_overrides'] ?? []);
            }
        } else {
            $this->syncCatalogMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []), $request->input('member_roles', []), $request->input('member_load_overrides', []));
        }

        $this->roster->reconcileCurrentTerm($committee);
        $this->ipcrSync->syncForCommittee($committee->id);
        $this->notifyRosterAdditions($committee, [], $validated['member_ids'] ?? [], $request->input('member_roles', []), $request->input('member_load_overrides', []));
        \App\Services\AuditLogger::logModelEvent($committee, 'created');

        return redirect()->back()->with('success', 'Committee created.');
    }

    public function updateCommittee(Request $request, GlobalCommittee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $validated = $this->validateCatalogPayload($request, $committee);

        $previousMemberIds = $committee->members()->pluck('users.id')->all();

        $committee->update([
            'name'              => $validated['name'],
            'head_id'           => $validated['head_id'] ?? null,
            'description'       => $validated['description'] ?? null,
            'fiscal_year'       => $validated['fiscal_year'] ?? null,
            'max_members'       => $validated['max_members'] ?? null,
            'scope_type'        => $validated['scope_type'] ?? $committee->scope_type,
            'school_year_id'    => $validated['school_year_id'] ?? null,
            'season_starts_at'  => $validated['season_starts_at'] ?? null,
            'season_ends_at'    => $validated['season_ends_at'] ?? null,
            'so_number'         => $validated['so_number'] ?? null,
            'issuance_id'       => $validated['issuance_id'] ?? null,
        ]);
        \App\Services\AuditLogger::logModelEvent($committee, 'updated');

        $committee->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                if (!empty($subData['id'])) {
                    $sub = GlobalCommittee::find($subData['id']);
                    if ($sub && $sub->parent_committee_id === $committee->id) {
                        $sub->update(['name' => $subData['name'], 'head_id' => $subData['head_id'] ?? null]);
                        $this->syncCatalogMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? [], $subData['member_roles'] ?? [], $subData['member_load_overrides'] ?? []);
                    }
                } else {
                    $sub = GlobalCommittee::create([
                        'name'                => $subData['name'],
                        'head_id'             => $subData['head_id'] ?? null,
                        'parent_committee_id' => $committee->id,
                    ]);
                    $this->syncCatalogMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? [], $subData['member_roles'] ?? [], $subData['member_load_overrides'] ?? []);
                }
            }
        } else {
            $newMemberIds = $validated['member_ids'] ?? [];
            $this->syncCatalogMembers($committee, $newMemberIds, $request->input('member_tasks', []), $request->input('member_roles', []), $request->input('member_load_overrides', []));

            $this->notifyRosterAdditions($committee, $previousMemberIds, $newMemberIds, $request->input('member_roles', []), $request->input('member_load_overrides', []));
            $this->notifyRosterRemovals($committee, $previousMemberIds, $newMemberIds);
        }

        $this->roster->reconcileCurrentTerm($committee);
        $this->ipcrSync->syncForCommittee($committee->id);

        return redirect()->back()->with('success', 'Committee updated.');
    }

    /**
     * Shared validation for storeCommittee()/updateCommittee() — folds in
     * the lifecycle/scope model, SO number/issuance tagging, and
     * per-member role + load-unit override fields the merged catalog
     * modal now captures (replacing the retired standalone Assign
     * Committee modal).
     */
    private function validateCatalogPayload(Request $request, ?GlobalCommittee $committee = null): array
    {
        return $request->validate([
            'name'                              => 'required|string|max:255',
            'head_id'                            => 'nullable|exists:users,id',
            'description'                        => 'nullable|string',
            'fiscal_year'                        => 'nullable|integer|min:2000|max:2100',
            'max_members'                        => 'nullable|integer|min:1|max:255',
            'has_subcommittees'                  => 'boolean',
            'member_ids'                         => 'nullable|array',
            'member_ids.*'                       => 'exists:users,id',
            'member_tasks'                       => 'nullable|array',
            'member_roles'                       => 'nullable|array',
            'member_roles.*'                     => ['nullable', Rule::in(['member', 'secretary', 'co_chair'])],
            'member_load_overrides'              => 'nullable|array',
            'member_load_overrides.*'            => 'nullable|numeric|min:0|max:5',
            'plan_ids'                           => 'nullable|array',
            'plan_ids.*'                         => 'exists:work_distribution_plans,id',
            'scope_type'                         => ['nullable', Rule::in([GlobalCommittee::SCOPE_PERPETUAL, GlobalCommittee::SCOPE_SCHOOL_YEAR, GlobalCommittee::SCOPE_SEASONAL])],
            'school_year_id'                     => 'nullable|required_if:scope_type,' . GlobalCommittee::SCOPE_SCHOOL_YEAR . '|exists:school_years,id',
            'season_starts_at'                   => 'nullable|required_if:scope_type,' . GlobalCommittee::SCOPE_SEASONAL . '|date',
            'season_ends_at'                      => 'nullable|required_if:scope_type,' . GlobalCommittee::SCOPE_SEASONAL . '|date|after_or_equal:season_starts_at',
            'so_number'                          => 'nullable|string|max:100',
            'issuance_id'                        => 'nullable|exists:issuances,id',
            'sub_committees'                     => 'nullable|array',
            'sub_committees.*.id'                => 'nullable|exists:committees,id',
            'sub_committees.*.name'               => 'required_with:sub_committees|string|max:255',
            'sub_committees.*.head_id'            => 'nullable|exists:users,id',
            'sub_committees.*.member_ids'         => 'nullable|array',
            'sub_committees.*.member_ids.*'       => 'exists:users,id',
            'sub_committees.*.member_tasks'        => 'nullable|array',
            'sub_committees.*.member_roles'        => 'nullable|array',
            'sub_committees.*.member_load_overrides' => 'nullable|array',
        ]);
    }

    /** Sends assignment-added notifications for members newly present in the roster diff. */
    private function notifyRosterAdditions(GlobalCommittee $committee, array $previousMemberIds, array $newMemberIds, array $roles, array $loadOverrides): void
    {
        $addedIds = array_diff($newMemberIds, $previousMemberIds);
        if (empty($addedIds)) return;

        foreach (User::whereIn('id', $addedIds)->get() as $member) {
            $role      = in_array($roles[$member->id] ?? 'member', ['secretary', 'co_chair'], true) ? $roles[$member->id] : 'member';
            $roleLabel = $member->id === $committee->head_id ? 'chairperson' : $role;
            $loadUnits = (float) ($loadOverrides[$member->id] ?? $committee->loadUnitsFor($roleLabel));
            $this->notifications->assignmentAdded($committee, $member, $roleLabel, $loadUnits);
        }
    }

    /** Sends assignment-removed notifications for members dropped from the roster diff. */
    private function notifyRosterRemovals(GlobalCommittee $committee, array $previousMemberIds, array $newMemberIds): void
    {
        $removedIds = array_diff($previousMemberIds, $newMemberIds);
        if (empty($removedIds)) return;

        foreach (User::whereIn('id', $removedIds)->get() as $member) {
            $this->notifications->assignmentRemoved($committee, $member, 'member');
        }
    }

    /**
     * Revoke a committee — ends its lifecycle deliberately (distinct from
     * is_active/delete): sets revoked_at/revoked_by/revocation_reason,
     * deactivates every active assignment this term, and notifies every
     * currently-active member + the chairperson.
     */
    public function revokeCommittee(Request $request, GlobalCommittee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $data = $request->validate([
            'revocation_reason' => 'nullable|string|max:1000',
        ]);

        $affectedMembers = $committee->members()->get();

        $committee->update([
            'revoked_at'         => now(),
            'revoked_by'         => $user->id,
            'revocation_reason'  => $data['revocation_reason'] ?? null,
        ]);

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        if ($currentTerm) {
            FacultyCommitteeAssignment::where('committee_id', $committee->id)
                ->where('academic_term_id', $currentTerm->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);
        }

        $this->notifications->revoked($committee, $data['revocation_reason'] ?? null, $affectedMembers);

        return redirect()->back()->with('success', 'Committee revoked.');
    }

    /**
     * Amend a committee — creates a new versioned committee row that
     * replaces this one (amended_from_committee_id points the NEW row back
     * to the OLD row), instead of mutating history in place. The old row is
     * revoked as part of the same action. New name/description/etc. come
     * from the same catalog payload shape as store/update.
     */
    public function amendCommittee(Request $request, GlobalCommittee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $validated = $this->validateCatalogPayload($request, $committee);

        $affectedMembers = $committee->members()->get();

        $newCommittee = GlobalCommittee::create([
            'name'                     => $validated['name'],
            'head_id'                  => $validated['head_id'] ?? null,
            'description'              => $validated['description'] ?? null,
            'fiscal_year'               => $validated['fiscal_year'] ?? null,
            'max_members'               => $validated['max_members'] ?? null,
            'scope_type'                => $validated['scope_type'] ?? $committee->scope_type,
            'school_year_id'             => $validated['school_year_id'] ?? null,
            'season_starts_at'           => $validated['season_starts_at'] ?? null,
            'season_ends_at'             => $validated['season_ends_at'] ?? null,
            'so_number'                  => $validated['so_number'] ?? null,
            'issuance_id'                => $validated['issuance_id'] ?? null,
            'amended_from_committee_id'  => $committee->id,
        ]);

        $newCommittee->workDistributionPlans()->sync($validated['plan_ids'] ?? []);
        $this->syncCatalogMembers($newCommittee, $validated['member_ids'] ?? [], $request->input('member_tasks', []), $request->input('member_roles', []), $request->input('member_load_overrides', []));

        $committee->update([
            'revoked_at'        => now(),
            'revoked_by'        => $user->id,
            'revocation_reason' => 'Amended into committee #' . $newCommittee->id,
        ]);

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        if ($currentTerm) {
            FacultyCommitteeAssignment::where('committee_id', $committee->id)
                ->where('academic_term_id', $currentTerm->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);
        }

        $this->roster->reconcileCurrentTerm($newCommittee);
        $this->ipcrSync->syncForCommittee($newCommittee->id);
        $this->notifications->amended($committee, $newCommittee, $affectedMembers);

        return redirect()->back()->with('success', 'Committee amended — a new version is now active.');
    }

    public function destroyCommittee(GlobalCommittee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $committee->delete();
        return redirect()->back()->with('success', 'Committee deleted.');
    }

    // ── "My Committees" — the logged-in user's own committee memberships ─────
    // across every term they've held one, since one employee can belong to
    // several committees simultaneously. Self-service, no permission gate
    // beyond being authenticated (route middleware already requires
    // accomplishments.view|faculty_loading.manage, which every employee with
    // a committee membership already needs to reach this page in practice).

    public function myCommittees(Request $request): Response
    {
        $user = auth()->user();

        $assignments = FacultyCommitteeAssignment::with(['committee:id,name,code,parent_committee_id,scope_type,revoked_at', 'academicTerm.schoolYear'])
            ->where('user_id', $user->id)
            ->orderByDesc('academic_term_id')
            ->get();

        $items = $assignments->map(function ($a) {
            $items = $this->ipcrRating->resolveSupportItems($a);
            $latestItem = $items->first();

            return [
                'assignment_id'  => $a->id,
                'committee_id'   => $a->committee_id,
                'committee_name' => $a->committee_name,
                'committee_code' => $a->committee?->code,
                'is_revoked'     => $a->committee?->revoked_at !== null,
                'scope_type'     => $a->committee?->scope_type,
                'role'           => $a->role,
                'is_chairperson' => $a->isChairperson(),
                'load_units'     => (float) $a->load_units,
                'status'         => $a->status,
                'term'           => $a->academicTerm ? ['id' => $a->academicTerm->id, 'label' => $a->academicTerm->full_label] : null,
                'rating_status'  => $latestItem ? [
                    'row_average' => $latestItem->row_average,
                    'ipcr_status' => $latestItem->ipcr->status ?? null,
                ] : null,
            ];
        });

        return Inertia::render('PerformanceManagement/Committees/MyCommittees', [
            'assignments' => $items,
            'authUser'    => $user->only('id', 'name'),
        ]);
    }

    private function syncCatalogMembers(GlobalCommittee $committee, array $memberIds, array $memberTasks, array $memberRoles = [], array $memberLoadOverrides = []): void
    {
        $syncData = [];
        foreach ($memberIds as $userId) {
            $role = in_array($memberRoles[$userId] ?? 'member', ['secretary', 'co_chair'], true) ? $memberRoles[$userId] : 'member';
            $syncData[$userId] = [
                'task'                => $memberTasks[$userId] ?? null,
                'role'                => $role,
                'load_units_override' => isset($memberLoadOverrides[$userId]) && $memberLoadOverrides[$userId] !== ''
                    ? (float) $memberLoadOverrides[$userId]
                    : null,
            ];
        }
        $committee->members()->sync($syncData);
    }

    // ── Committee detail / performance view ───────────────────────────────────

    public function show(Request $request, Committee $committee): Response
    {
        // Admins see everything; chairpersons and members see their own
        // committee (or its parent/subs). Everyone else is blocked below,
        // after membership is resolved.
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $committee->load(['head:id,name,position', 'workDistributionPlans:id,success_indicator,rated_by']);

        $assignments = FacultyCommitteeAssignment::with(['faculty:id,name,position'])
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

        // Member-centric: each active assignment's own resolved IPCR V2
        // Support Item(s) for the current rating period — replaces the old
        // committee-level-tagged-plan grouping, which could disagree with
        // what EmployeeFunctionSyncService actually resolves per member
        // (it prefers the assignment's OWN tagged plans, falling back to
        // the committee's only when the assignment has none of its own).
        $members = $assignments->map(function ($a) {
            $items = $this->ipcrRating->resolveSupportItems($a)->map(fn ($item) => [
                'id'                    => $item->id,
                'label'                 => $item->label,
                'success_indicator'     => $item->success_indicator,
                'target'                => $item->target,
                'actual_accomplishment' => $item->actual_accomplishment,
                'mov_link'              => $item->mov_link,
                'quality_rating'        => $item->quality_rating,
                'efficiency_rating'     => $item->efficiency_rating,
                'timeliness_rating'     => $item->timeliness_rating,
                'row_average'           => $item->row_average,
                'ipcr_status'           => $item->ipcr->status,
            ])->values();

            return [
                'assignment_id'  => $a->id,
                'user_id'        => $a->faculty->id,
                'user_name'      => $a->faculty->name,
                'user_position'  => $a->faculty->position,
                'role'           => $a->role,
                'is_chairperson' => $a->isChairperson(),
                'items'          => $items,
            ];
        });

        $globalCommittee = GlobalCommittee::with(['revokedBy:id,name', 'amendedFrom:id,name', 'amendedInto:id,name', 'issuance:id,title,control_number,reference_number'])
            ->find($committee->id);

        $auditTrail = \App\Models\AuditLog::where('auditable_type', GlobalCommittee::class)
            ->where('auditable_id', $committee->id)
            ->orWhere(function ($q) use ($committee) {
                $q->where('auditable_type', GlobalCommittee::class)
                  ->where(function ($sub) use ($committee) {
                      $sub->whereJsonContains('old_values->amended_into_committee_id', $committee->id)
                          ->orWhereJsonContains('new_values->amended_into_committee_id', $committee->id);
                  });
            })
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'user_name'  => $log->user?->name ?? 'System',
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('PerformanceManagement/Committees/Show', [
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
                'scope_type'             => $globalCommittee?->scope_type,
                'season_starts_at'       => $globalCommittee?->season_starts_at?->toDateString(),
                'season_ends_at'         => $globalCommittee?->season_ends_at?->toDateString(),
                'so_number'              => $globalCommittee?->so_number,
                'issuance'               => $globalCommittee?->issuance ? ['id' => $globalCommittee->issuance->id, 'label' => $globalCommittee->issuance->display_number ?? $globalCommittee->issuance->title] : null,
                'is_revoked'             => $globalCommittee?->isRevoked() ?? false,
                'revoked_at'             => $globalCommittee?->revoked_at?->toIso8601String(),
                'revoked_by'             => $globalCommittee?->revokedBy?->only('id', 'name'),
                'revocation_reason'      => $globalCommittee?->revocation_reason,
                'amended_from'           => $globalCommittee?->amendedFrom ? ['id' => $globalCommittee->amendedFrom->id, 'name' => $globalCommittee->amendedFrom->name] : null,
                'amended_into'           => $globalCommittee?->amendedInto ? ['id' => $globalCommittee->amendedInto->id, 'name' => $globalCommittee->amendedInto->name] : null,
            ],
            'members'        => $members,
            'terms'          => $terms,
            'selectedTermId' => (int) $termId,
            'authUser'       => $authUser->only('id', 'name'),
            'isChairperson'  => $isChairperson,
            'canManage'      => $canManage,
            'auditTrail'     => $auditTrail,
            'tasks'            => \App\Models\CommitteeTask::with(['assignees:id,name', 'plan:id,success_indicator', 'period:id,label', 'updates.user:id,name'])
                                    ->withCount('updates')
                                    ->where('committee_id', $committee->id)
                                    ->forPeriod(\App\Models\IPCRRatingPeriod::current()->value('id'))
                                    ->orderBy('sort_order')
                                    ->get(),
            'boardMembers'     => $assignments->map(fn ($a) => $a->faculty->only('id', 'name'))->unique('id')->values(),
            'canManageBoard'   => app(\App\Services\CommitteeBoardService::class)->canManageBoard($authUser, GlobalCommittee::find($committee->id)),
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

    // ── Issuance search for the "Link Issuance" picker (JSON) ─────────────────

    public function searchIssuances(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'nullable|string|max:200']);

        $issuances = \App\Models\Issuance::query()
            ->when($data['q'] ?? null, fn ($q) => $q->where(function ($sub) use ($data) {
                $sub->where('title', 'like', "%{$data['q']}%")
                    ->orWhere('control_number', 'like', "%{$data['q']}%")
                    ->orWhere('reference_number', 'like', "%{$data['q']}%");
            }))
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'title', 'control_number', 'reference_number']);

        return response()->json($issuances->map(fn ($i) => [
            'id'    => $i->id,
            'label' => ($i->reference_number ?: $i->control_number) . ' — ' . $i->title,
        ]));
    }

    // ── Load-unit conflict check for a member being added/edited (JSON) ──────
    // Sums a member's active committee load_units for the term and flags
    // (soft warning, never a hard block) when it looks abnormally high.

    public function loadConflictCheck(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'user_id'          => 'required|integer|exists:users,id',
            'term_id'          => 'required|integer|exists:academic_terms,id',
            'additional_units' => 'nullable|numeric|min:0|max:5',
            'exclude_committee_id' => 'nullable|integer',
        ]);

        $threshold = (float) config('committees.load_conflict_threshold', 3.0);

        $currentTotal = (float) FacultyCommitteeAssignment::where('user_id', $data['user_id'])
            ->where('academic_term_id', $data['term_id'])
            ->where('status', 'active')
            ->when($data['exclude_committee_id'] ?? null, fn ($q) => $q->where('committee_id', '!=', $data['exclude_committee_id']))
            ->sum('load_units');

        $projectedTotal = $currentTotal + (float) ($data['additional_units'] ?? 0);

        return response()->json([
            'current_total'   => round($currentTotal, 2),
            'projected_total' => round($projectedTotal, 2),
            'threshold'       => $threshold,
            'exceeds'         => $projectedTotal > $threshold,
        ]);
    }

    // ── Roster/compliance export ──────────────────────────────────────────────

    public function exportRosterPdf(Request $request, GlobalCommittee $committee)
    {
        $termId = $request->integer('term_id') ?: null;
        $bytes  = $this->pdfExport->exportRoster($committee, $termId);

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . str($committee->name)->slug() . '-roster.pdf"',
        ]);
    }

    public function exportRosterExcel(Request $request, GlobalCommittee $committee)
    {
        $termId = $request->integer('term_id') ?: null;
        $path   = $this->excelExport->exportRoster($committee, $termId);

        return response()->download($path, str($committee->name)->slug() . '-roster.xlsx')->deleteFileAfterSend(true);
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

        if ($data['committee_id'] ?? null) {
            $committee = Committee::find($data['committee_id']);
            if ($committee) {
                if ($request->missing('load_units')) {
                    $data['load_units'] = $committee->loadUnitsFor($data['role']);
                }
                // Sync tagged WDP plans
                $committee->workDistributionPlans()->sync($data['plan_ids'] ?? []);
                $this->ipcrSync->syncForCommittee($committee->id);
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
        $this->ipcrSync->syncForUser(User::find($data['user_id']));

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
                $this->ipcrSync->syncForCommittee($committee->id);
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
        $this->ipcrSync->syncForUser($committeeAssignment->faculty);

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
        $this->ipcrSync->syncForUser(User::find($userId));

        return back()->with('success', 'Committee assignment removed.');
    }

    // ── Rate a member (chairperson / admin only) — writes IPCR V2 ────────────

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
            'support_item_id'   => 'required|integer|exists:ipcr_v2_support_items,id',
            'accomplishment'    => 'nullable|string|max:1000',
            'mov_link'          => 'nullable|string|max:500',
            'quality_rating'    => 'required|integer|min:1|max:5',
            'efficiency_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
        ]);

        $validItemIds = $this->ipcrRating->resolveSupportItems($committeeAssignment)->pluck('id');
        abort_unless($validItemIds->contains((int) $data['support_item_id']), 404, 'This item does not belong to this committee assignment.');

        $item = \App\Models\IPCRV2\IpcrV2SupportItem::findOrFail($data['support_item_id']);
        $rated = $this->ipcrRating->rate($item, $data);

        if ($committeeAssignment->committee_id && $rated->row_average !== null) {
            $globalCommittee = GlobalCommittee::find($committeeAssignment->committee_id);
            if ($globalCommittee) {
                $this->notifications->ratingReceived($globalCommittee, $committeeAssignment->faculty, (float) $rated->row_average);
            }
        }

        return back()->with('success', 'Rating saved.');
    }

    // ── Link / replace this assignment's own Work Distribution Plans ─────────
    // (distinct from the committee-level workDistributionPlans() tagging —
    // this lets one specific faculty member's committee assignment carry its
    // own plan links, e.g. when the assignment has no unit load and should
    // surface as a Support Function on their IPCR.)

    public function syncPlans(Request $request, FacultyCommitteeAssignment $committeeAssignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'plan_ids'   => 'nullable|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $committeeAssignment->workDistributionPlans()->sync($data['plan_ids'] ?? []);

        return back()->with('success', 'Work Distribution Plans updated for this committee assignment.');
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
            'plan_ids'       => $a->workDistributionPlans->pluck('id')->toArray(),
            'faculty'        => $a->faculty ? $a->faculty->only('id', 'name') : null,
            'committee'      => $a->committee ? ['id' => $a->committee->id, 'name' => $a->committee->name, 'code' => $a->committee->code, 'parent_committee_id' => $a->committee->parent_committee_id] : null,
            'term'           => $a->academicTerm ? ['id' => $a->academicTerm->id, 'label' => $a->academicTerm->full_label] : null,
        ];
    }
}
