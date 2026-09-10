<?php

namespace App\Services;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\PerformanceManagement\WorkDistributionPlanClassifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Syncs a faculty member's Core/Support Function rows from Faculty Loading —
 * Load Assignments, Committee Assignments, and personnel-assigned Work
 * Distribution Plans — mirroring the resolution rules of v1's
 * FacultyIPCRBaselineService, adapted to v2's one-row-per-function model
 * (a function attaches to N plans via the many-to-many, rather than v1
 * materializing one row per tagged plan).
 *
 * WDP resolution per source, matching v1:
 *   - Teaching load (no designation): WDPs tagged load_source='teaching',
 *     or a classifier auto-fallback plan when none are tagged.
 *   - Designation-backed load: union of WDPs tagged on the Designation's
 *     Category and on the Designation itself, or a classifier fallback.
 *   - Other typed load (no designation, not teaching — e.g. raw research):
 *     WDPs tagged load_source matching the assignment_type, or a classifier
 *     fallback.
 *   - Committee assignment: its own explicit WDP link, or a classifier
 *     fallback.
 *   - Personnel-assigned plan (plan_user): attached directly; function_type
 *     is read from the plan's own AgencyOutcome.function_type.
 *
 * Core vs Support: any load-bearing group (load_units > 0) is Core,
 * everything else (zero-load designation, most committee memberships) is
 * Support — mirroring WorkDistributionPlanClassifier::functionTypeFor().
 * Core weight_percent is each group's share of the combined unit pool
 * (all LoadAssignment + FacultyCommitteeAssignment load_units for this
 * user/term) — but normalized against 100% MINUS any manually-declared
 * Core Function weight (EmployeeFunctionController — a row with no
 * sync_source_key, since nothing in this service writes one), not a flat
 * 100%. A manually-entered Core Function already reserves its own slice
 * of the person's Core budget; without this, load-based Core rows would
 * separately re-normalize to their own full 100% and the two pools would
 * double up (IpcrV2GenerationService's "Core weights sum to 100%" check
 * would then correctly — but confusingly — reject the combined total).
 */
class EmployeeFunctionSyncService
{
    public function __construct(
        private WorkDistributionPlanClassifier $classifier = new WorkDistributionPlanClassifier()
    ) {}

    public function syncFromFacultyLoading(User $user): void
    {
        $term = AcademicTerm::where('is_current', true)->first();
        if (! $term) {
            return;
        }

        DB::transaction(function () use ($user, $term) {
            $assignments = LoadAssignment::with(['subject', 'designation.category'])
                ->where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->get();

            $committeeAssignments = FacultyCommitteeAssignment::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->where('status', 'active')
                ->get();

            $personnelPlanIds = DB::table('plan_user')->where('user_id', $user->id)->pluck('work_distribution_plan_id');
            $personnelPlans = WorkDistributionPlan::whereIn('id', $personnelPlanIds)
                ->with('performanceIndicator.agencyOutcome')
                ->get();

            [$rawTeaching, $otherAssignments] = $assignments->partition(
                fn ($a) => ! $a->designation_id && $a->assignment_type === 'teaching'
            );
            [$designationAssignments, $typedAssignments] = $otherAssignments->partition(
                fn ($a) => (bool) $a->designation_id
            );

            $totalUnits = (float) $assignments->sum('load_units') + (float) $committeeAssignments->sum('load_units');

            // Manually-declared Core Functions (EmployeeFunctionController —
            // no sync_source_key, since nothing in this service ever writes
            // one) reserve their own slice of this person's 100% Core
            // budget; load-based Core weight below must be normalized to
            // what's LEFT, not a flat 100%, or the two independently-
            // computed pools double up (e.g. a manually-entered 100%
            // Core Function plus load-based Core rows separately
            // re-normalized to their own 100% — sum 200%). Evergreen
            // (no academic_term_id), so this is user-scoped only, matching
            // how these rows are actually stored.
            $manualCoreWeight = (float) EmployeeFunction::where('user_id', $user->id)
                ->core()
                ->whereNull('sync_source_key')
                ->sum('weight_percent');
            $coreWeightBudget = max(0.0, 100.0 - $manualCoreWeight);

            $keptIds = [];

            foreach ($this->groupBySubject($rawTeaching) as $group) {
                $keptIds[] = $this->syncTeachingGroup($user, $term, $group, $totalUnits, $coreWeightBudget);
            }

            foreach ($this->groupByDesignation($designationAssignments) as $group) {
                $keptIds[] = $this->syncLoadGroup($user, $term, $group, $totalUnits, $coreWeightBudget, fn ($representative) => $this->designationPlanIds($representative));
            }

            foreach ($this->groupByType($typedAssignments) as $group) {
                $keptIds[] = $this->syncLoadGroup($user, $term, $group, $totalUnits, $coreWeightBudget, fn ($representative) => $this->typedFrameworkPlanIds($representative->assignment_type));
            }

            foreach ($committeeAssignments as $ca) {
                $keptIds[] = $this->syncCommitteeAssignment($user, $term, $ca, $totalUnits, $coreWeightBudget);
            }

            foreach ($personnelPlans as $plan) {
                $keptIds[] = $this->syncPersonnelPlan($user, $term, $plan);
            }

            // Detach any prior auto-synced row for this user/term no longer
            // represented — UNLESS it has real IPCR V2 accomplishment data
            // logged against it (core or support), in which case it's left
            // for manual review (never silently drop rated/logged work).
            // Deletes one-by-one (not a bulk query-builder delete) so
            // EmployeeFunction::booted()'s `deleted` event fires for each
            // row — that event is what prunes any orphaned IPCR V2 item
            // this row had already materialized; a bulk delete() bypasses
            // Eloquent model events entirely and would silently skip that.
            EmployeeFunction::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->autoSynced()
                ->whereNotIn('id', array_filter($keptIds))
                ->whereDoesntHave('ipcrV2CoreItems', fn ($q) => $q->whereNotNull('actual_accomplishment')->where('actual_accomplishment', '!=', ''))
                ->whereDoesntHave('ipcrV2SupportItems', fn ($q) => $q->whereNotNull('actual_accomplishment')->where('actual_accomplishment', '!=', ''))
                ->get()
                ->each(fn (EmployeeFunction $function) => $function->delete());
        });
    }

    private function syncTeachingGroup(User $user, AcademicTerm $term, array $group, float $totalUnits, float $coreWeightBudget = 100.0): ?int
    {
        $weight = $totalUnits > 0 ? round(($group['units'] / $totalUnits) * $coreWeightBudget, 2) : 0;

        $row = $this->upsertRow($user, $term, 'load_assignment:' . $group['representative']->id, [
            'function_type' => EmployeeFunction::TYPE_CORE,
            'label' => $group['label'],
            'weight_percent' => $weight,
            'load_assignment_id' => $group['representative']->id,
        ]);

        if (! $row) {
            return null;
        }

        $taggedPlanIds = WorkDistributionPlan::where('load_source', 'teaching')->pluck('id');
        $row->workDistributionPlans()->sync(
            $taggedPlanIds->isNotEmpty()
                ? $taggedPlanIds->all()
                : [$this->classifier->defaultPlanForLoadAssignment($group['representative'])->id]
        );

        return $row->id;
    }

    /**
     * Shared sync for designation-backed and other-typed raw load groups —
     * identical shape (Core/Support by unit load, explicit tag else
     * classifier fallback), differing only in how plan ids are resolved.
     */
    private function syncLoadGroup(User $user, AcademicTerm $term, array $group, float $totalUnits, float $coreWeightBudget, \Closure $resolveExplicitPlanIds): ?int
    {
        $hasUnits = $group['units'] > 0;
        $weight = $hasUnits && $totalUnits > 0 ? round(($group['units'] / $totalUnits) * $coreWeightBudget, 2) : null;

        $row = $this->upsertRow($user, $term, 'load_assignment:' . $group['representative']->id, [
            'function_type' => $hasUnits ? EmployeeFunction::TYPE_CORE : EmployeeFunction::TYPE_SUPPORT,
            'label' => $group['label'],
            'output_outcome' => $group['output_outcome'] ?? null,
            'weight_percent' => $weight,
            'load_assignment_id' => $group['representative']->id,
        ]);

        if (! $row) {
            return null;
        }

        $explicitPlanIds = $resolveExplicitPlanIds($group['representative']);
        $row->workDistributionPlans()->sync(
            $explicitPlanIds->isNotEmpty()
                ? $explicitPlanIds->all()
                : [$this->classifier->defaultPlanForLoadAssignment($group['representative'])->id]
        );

        return $row->id;
    }

    private function syncCommitteeAssignment(User $user, AcademicTerm $term, FacultyCommitteeAssignment $ca, float $totalUnits, float $coreWeightBudget = 100.0): ?int
    {
        $hasUnits = $ca->hasUnitLoad();
        $weight = $hasUnits && $totalUnits > 0 ? round(((float) $ca->load_units / $totalUnits) * $coreWeightBudget, 2) : null;

        $row = $this->upsertRow($user, $term, 'committee_assignment:' . $ca->id, [
            'function_type' => $hasUnits ? EmployeeFunction::TYPE_CORE : EmployeeFunction::TYPE_SUPPORT,
            'label' => $ca->committee_name,
            'weight_percent' => $weight,
        ]);

        if (! $row) {
            return null;
        }

        // Resolution order: (1) a plan explicitly tagged on THIS assignment
        // (faculty_committee_assignment_work_distribution_plan — same pivot
        // v1's FacultyIPCRBaselineService reads), (2) the committee's own
        // catalog-level tagged plans (committee_work_distribution_plan —
        // what CommitteeAssignmentController::store()/update()'s "plan_ids"
        // field and the DM/PMS committee editor actually write to; every
        // member of the same committee shares this tag), (3) the
        // classifier's auto-generated fallback. Without step (2), a plan
        // tagged via the committee assignment FORM never reached any
        // member's Employee Function — the form's plan_ids only ever
        // wrote to the committee, never the per-assignment pivot this
        // method originally read exclusively.
        $explicitPlanIds = $ca->workDistributionPlans()->pluck('work_distribution_plans.id');
        if ($explicitPlanIds->isEmpty() && $ca->committee_id) {
            $explicitPlanIds = $ca->committee?->workDistributionPlans()->pluck('work_distribution_plans.id') ?? collect();
        }

        $row->workDistributionPlans()->sync(
            $explicitPlanIds->isNotEmpty()
                ? $explicitPlanIds->all()
                : [$this->classifier->defaultPlanForCommitteeAssignment($ca)->id]
        );

        return $row->id;
    }

    /**
     * A plan assigned directly to the person (plan_user) — not tied to any
     * load, so its Core/Support placement mirrors the plan's own tagged
     * AgencyOutcome.function_type rather than a unit-load check.
     */
    private function syncPersonnelPlan(User $user, AcademicTerm $term, WorkDistributionPlan $plan): ?int
    {
        $isCore = $plan->performanceIndicator?->agencyOutcome?->function_type === WorkDistributionPlanClassifier::CORE_FUNCTIONS;

        $row = $this->upsertRow($user, $term, 'personnel_plan:' . $plan->id, [
            'function_type' => $isCore ? EmployeeFunction::TYPE_CORE : EmployeeFunction::TYPE_SUPPORT,
            'label' => $plan->success_indicator ?? 'Personnel Assignment',
            'weight_percent' => null,
        ]);

        if (! $row) {
            return null;
        }

        $row->workDistributionPlans()->sync([$plan->id]);

        return $row->id;
    }

    /**
     * Returns null (skips creation) when this exact source was previously
     * manually deleted by the user via EmployeeFunctionController::destroy()
     * — recorded in employee_function_sync_dismissals, keyed by the same
     * sourceKey. Only blocks CREATING a new row for a dismissed key; if a
     * row already exists (e.g. re-sync running again before any deletion
     * happened), it's updated normally — dismissal only means "don't bring
     * this back after I deleted it," not "never touch it again."
     */
    private function upsertRow(User $user, AcademicTerm $term, string $sourceKey, array $attributes): ?EmployeeFunction
    {
        $criteria = [
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'source_type' => EmployeeFunction::SOURCE_LOAD_ASSIGNMENT,
            'sync_source_key' => $sourceKey,
        ];

        $existing = EmployeeFunction::where($criteria)->first();

        if (! $existing) {
            $isDismissed = \App\Models\EmployeeFunctionSyncDismissal::where('user_id', $user->id)
                ->where('sync_source_key', $sourceKey)
                ->exists();

            if ($isDismissed) {
                return null;
            }
        }

        return EmployeeFunction::updateOrCreate($criteria, $attributes + ['created_by' => $user->id]);
    }

    /**
     * Union of WDPs tagged on the assignment's Designation Category (shared
     * default for every designation under it) and on the Designation itself
     * (additional, on top of the category's).
     */
    private function designationPlanIds(LoadAssignment $assignment): Collection
    {
        if (! $assignment->designation) {
            return collect();
        }

        return ($assignment->designation->category?->workDistributionPlans()->pluck('work_distribution_plans.id') ?? collect())
            ->merge($assignment->designation->workDistributionPlans()->pluck('work_distribution_plans.id'))
            ->unique()->values();
    }

    private function typedFrameworkPlanIds(string $assignmentType): Collection
    {
        return WorkDistributionPlan::where('load_source', $assignmentType)->pluck('id');
    }

    /**
     * @return array<int, array{label: string, units: float, representative: LoadAssignment}>
     */
    private function groupBySubject(Collection $assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            if (! $assignment->subject_id) {
                continue;
            }

            $key = 'subject_' . $assignment->subject_id;
            $groups[$key]['label'] ??= $assignment->subject?->name ?? 'Teaching Load';
            $groups[$key]['units'] = ($groups[$key]['units'] ?? 0) + (float) $assignment->load_units;
            $groups[$key]['representative'] ??= $assignment;
        }

        return $groups;
    }

    /**
     * @return array<int, array{label: string, units: float, representative: LoadAssignment, output_outcome: ?string}>
     */
    private function groupByDesignation(Collection $assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            $key = 'designation_' . $assignment->designation_id;
            $groups[$key]['label'] ??= $assignment->description ?? ($assignment->designation?->name ?? 'Designation');
            // The Designation's own description doubles as its Output/Outcome
            // Statement (relabeled in the Designations UI) — a short blurb of
            // what this designation, as a whole, produces. Carried into the
            // auto-synced EmployeeFunction row so it snapshots onto IPCR V2
            // core/support items the same way label/success_indicator do.
            $groups[$key]['output_outcome'] ??= $assignment->designation?->description;
            $groups[$key]['units'] = ($groups[$key]['units'] ?? 0) + (float) $assignment->load_units;
            $groups[$key]['representative'] ??= $assignment;
        }

        return $groups;
    }

    /**
     * @return array<int, array{label: string, units: float, representative: LoadAssignment}>
     */
    private function groupByType(Collection $assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            $key = 'type_' . $assignment->assignment_type;
            $groups[$key]['label'] ??= ucfirst($assignment->assignment_type) . ' Load';
            $groups[$key]['units'] = ($groups[$key]['units'] ?? 0) + (float) $assignment->load_units;
            $groups[$key]['representative'] ??= $assignment;
        }

        return $groups;
    }
}
