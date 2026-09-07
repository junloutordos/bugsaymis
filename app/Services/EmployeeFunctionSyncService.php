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
 * user/term), so IpcrV2GenerationService's "Core weights sum to 100%"
 * check stays correct regardless of which source contributed the units.
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

            $keptIds = [];

            foreach ($this->groupBySubject($rawTeaching) as $group) {
                $keptIds[] = $this->syncTeachingGroup($user, $term, $group, $totalUnits);
            }

            foreach ($this->groupByDesignation($designationAssignments) as $group) {
                $keptIds[] = $this->syncLoadGroup($user, $term, $group, $totalUnits, fn ($representative) => $this->designationPlanIds($representative));
            }

            foreach ($this->groupByType($typedAssignments) as $group) {
                $keptIds[] = $this->syncLoadGroup($user, $term, $group, $totalUnits, fn ($representative) => $this->typedFrameworkPlanIds($representative->assignment_type));
            }

            foreach ($committeeAssignments as $ca) {
                $keptIds[] = $this->syncCommitteeAssignment($user, $term, $ca, $totalUnits);
            }

            foreach ($personnelPlans as $plan) {
                $keptIds[] = $this->syncPersonnelPlan($user, $term, $plan);
            }

            // Detach any prior auto-synced row for this user/term no longer
            // represented — UNLESS it has real IPCR V2 accomplishment data
            // logged against it (core or support), in which case it's left
            // for manual review (never silently drop rated/logged work).
            EmployeeFunction::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->autoSynced()
                ->whereNotIn('id', $keptIds)
                ->whereDoesntHave('ipcrV2CoreItems', fn ($q) => $q->whereNotNull('actual_accomplishment')->where('actual_accomplishment', '!=', ''))
                ->whereDoesntHave('ipcrV2SupportItems', fn ($q) => $q->whereNotNull('actual_accomplishment')->where('actual_accomplishment', '!=', ''))
                ->delete();
        });
    }

    private function syncTeachingGroup(User $user, AcademicTerm $term, array $group, float $totalUnits): int
    {
        $weight = $totalUnits > 0 ? round(($group['units'] / $totalUnits) * 100, 2) : 0;

        $row = $this->upsertRow($user, $term, 'load_assignment:' . $group['representative']->id, [
            'function_type' => EmployeeFunction::TYPE_CORE,
            'label' => $group['label'],
            'weight_percent' => $weight,
            'load_assignment_id' => $group['representative']->id,
        ]);

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
    private function syncLoadGroup(User $user, AcademicTerm $term, array $group, float $totalUnits, \Closure $resolveExplicitPlanIds): int
    {
        $hasUnits = $group['units'] > 0;
        $weight = $hasUnits && $totalUnits > 0 ? round(($group['units'] / $totalUnits) * 100, 2) : null;

        $row = $this->upsertRow($user, $term, 'load_assignment:' . $group['representative']->id, [
            'function_type' => $hasUnits ? EmployeeFunction::TYPE_CORE : EmployeeFunction::TYPE_SUPPORT,
            'label' => $group['label'],
            'weight_percent' => $weight,
            'load_assignment_id' => $group['representative']->id,
        ]);

        $explicitPlanIds = $resolveExplicitPlanIds($group['representative']);
        $row->workDistributionPlans()->sync(
            $explicitPlanIds->isNotEmpty()
                ? $explicitPlanIds->all()
                : [$this->classifier->defaultPlanForLoadAssignment($group['representative'])->id]
        );

        return $row->id;
    }

    private function syncCommitteeAssignment(User $user, AcademicTerm $term, FacultyCommitteeAssignment $ca, float $totalUnits): int
    {
        $hasUnits = $ca->hasUnitLoad();
        $weight = $hasUnits && $totalUnits > 0 ? round(((float) $ca->load_units / $totalUnits) * 100, 2) : null;

        $row = $this->upsertRow($user, $term, 'committee_assignment:' . $ca->id, [
            'function_type' => $hasUnits ? EmployeeFunction::TYPE_CORE : EmployeeFunction::TYPE_SUPPORT,
            'label' => $ca->committee_name,
            'weight_percent' => $weight,
        ]);

        $explicitPlanIds = $ca->workDistributionPlans()->pluck('work_distribution_plans.id');
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
    private function syncPersonnelPlan(User $user, AcademicTerm $term, WorkDistributionPlan $plan): int
    {
        $isCore = $plan->performanceIndicator?->agencyOutcome?->function_type === WorkDistributionPlanClassifier::CORE_FUNCTIONS;

        $row = $this->upsertRow($user, $term, 'personnel_plan:' . $plan->id, [
            'function_type' => $isCore ? EmployeeFunction::TYPE_CORE : EmployeeFunction::TYPE_SUPPORT,
            'label' => $plan->success_indicator ?? 'Personnel Assignment',
            'weight_percent' => null,
        ]);

        $row->workDistributionPlans()->sync([$plan->id]);

        return $row->id;
    }

    private function upsertRow(User $user, AcademicTerm $term, string $sourceKey, array $attributes): EmployeeFunction
    {
        return EmployeeFunction::updateOrCreate(
            [
                'user_id' => $user->id,
                'academic_term_id' => $term->id,
                'source_type' => EmployeeFunction::SOURCE_LOAD_ASSIGNMENT,
                'sync_source_key' => $sourceKey,
            ],
            $attributes + ['created_by' => $user->id]
        );
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
     * @return array<int, array{label: string, units: float, representative: LoadAssignment}>
     */
    private function groupByDesignation(Collection $assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            $key = 'designation_' . $assignment->designation_id;
            $groups[$key]['label'] ??= $assignment->description ?? ($assignment->designation?->name ?? 'Designation');
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
