<?php

namespace App\Services\PerformanceManagement;

use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a faculty member's IPCR baseline from Faculty Loading:
 * attaches the faculty framework WDPs (tagged via load_source), the WDPs
 * linked to their committees, any WDPs they are assigned to as personnel,
 * and any WDPs linked directly to their own designation-backed load
 * assignments / committee assignments — then personalizes each framework
 * row's individual_target from their actual load assignments (subjects,
 * sections, units).
 *
 * A LoadAssignment backed by a Designation (e.g. ACIDAA, Prefect of
 * Discipline) inherits the union of whatever WDPs are tagged on that
 * Designation's own Category "mother record" AND whatever is tagged on the
 * Designation itself — the category tag is a shared default for every
 * designation under it, a designation's own tag is additional on top of
 * that. A LoadAssignment with no designation (a raw per-subject teaching
 * load) is instead covered by any framework plan explicitly tagged with a
 * matching `load_source` (e.g. `teaching`) — one shared row, personalized
 * to list every such assignment as its own bullet in its target. Whatever
 * isn't covered by either mechanism falls back to its own dedicated,
 * auto-classified Core/Support Functions WDP — nothing is merged there, so
 * an untagged faculty member teaching two subjects gets two separate rows.
 */
class FacultyIPCRBaselineService
{
    public function __construct(private WorkDistributionPlanClassifier $classifier = new WorkDistributionPlanClassifier())
    {
    }

    /**
     * @return array{attached: int, personalized: int}
     */
    public function generate(EmployeeIPCR $ipcr): array
    {
        $ipcr->loadMissing(['user', 'period']);
        $user   = $ipcr->user;
        $period = $ipcr->period;

        $assignments = $this->loadAssignmentsFor($user, $period);

        // (a) Framework plans matching the teacher's load types
        $loadTypes = $assignments->pluck('assignment_type')->filter()->unique()->values();
        $frameworkPlans = WorkDistributionPlan::forFiscalYear($period?->year)
            ->whereIn('load_source', $loadTypes)
            ->get();

        // (b) Plans linked to the teacher's active committee assignments
        $committeeAssignments = FacultyCommitteeAssignment::where('user_id', $user->id)
            ->where('status', 'active')
            ->when($period?->start_date && $period?->end_date, function ($q) use ($period) {
                $termIds = AcademicTerm::where('start_date', '<=', $period->end_date)
                    ->where('end_date', '>=', $period->start_date)
                    ->pluck('id');
                $q->whereIn('academic_term_id', $termIds);
            })
            ->get();

        $committeeIds = $committeeAssignments->pluck('committee_id')->filter();

        $committeePlanIds = $committeeIds->isEmpty() ? collect() : DB::table('committee_work_distribution_plan')
            ->whereIn('committee_id', $committeeIds)
            ->pluck('work_distribution_plan_id');

        // (c) Plans assigned to the teacher as personnel
        $personnelPlanIds = DB::table('plan_user')
            ->where('user_id', $user->id)
            ->pluck('work_distribution_plan_id');

        // (d) Plans linked directly to the teacher's own load assignments /
        // committee assignments via the new pivots. Any assignment with no
        // explicit link of its own falls back to the Core/Support default.
        $directPlanIds = $this->directPlanIdsFor($assignments, $committeeAssignments, $period?->year);

        $attachIds = WorkDistributionPlan::whereIn(
                'id',
                $frameworkPlans->pluck('id')
                    ->merge($committeePlanIds)
                    ->merge($personnelPlanIds)
                    ->merge($directPlanIds)
                    ->unique()
            )
            ->forFiscalYear($period?->year)
            ->pluck('id')
            ->all();

        $before = $ipcr->plans()->pluck('work_distribution_plans.id');
        $ipcr->plans()->syncWithoutDetaching($attachIds);

        // Personalize framework rows that don't have a target yet
        $existingTargets = DB::table('employee_ipcrs_plan')
            ->where('ipcr_id', $ipcr->id)
            ->pluck('individual_target', 'plan_id');

        $personalized = 0;
        foreach ($frameworkPlans as $plan) {
            if (filled($existingTargets[$plan->id] ?? null)) {
                continue; // never clobber a target the teacher already wrote
            }

            $target = $this->buildTarget($assignments, $plan->load_source);
            if ($target === null) {
                continue;
            }

            $ipcr->plans()->updateExistingPivot($plan->id, ['individual_target' => $target]);
            $personalized++;
        }

        return [
            'attached'     => count(array_diff($attachIds, $before->all())),
            'personalized' => $personalized,
        ];
    }

    /**
     * Resolve WDP ids linked directly to this teacher's own load assignments
     * and committee assignments.
     *
     * LoadAssignment rows backed by a Designation (admin/committee/etc. via
     * DesignationService::assign()) inherit the UNION of whatever WDPs are
     * tagged on that Designation's Category "mother record" AND whatever is
     * tagged on the Designation itself — the category tag is a shared
     * default for every designation under it, while a designation's own tag
     * is additional, for when that specific designation needs its own plan
     * on top (e.g. a Math Coordinator needing a Math-specific plan beyond
     * the shared Coordinatorship plan).
     *
     * A LoadAssignment with no designation (a raw per-subject teaching load)
     * is instead covered by a "framework" plan — one explicitly tagged with
     * `load_source` matching its assignment_type (e.g. `teaching`), attached
     * globally in generate() part (a) and personalized in buildTarget() to
     * list every such assignment as its own bullet in that one row's target.
     *
     * Any assignment covered by NEITHER mechanism falls back to the
     * per-assignment auto-classified Core/Support default: a unit load (> 0)
     * becomes its own dedicated Core Functions row, no unit load becomes
     * Support Functions, one row per distinct assignment, never merged. This
     * guarantees every load assignment shows up somewhere on the IPCR even
     * before CID/HR tags its category, designation, or load type.
     *
     * FacultyCommitteeAssignment rows use their own explicit WDP link (set
     * per assignment, since committee membership is individual); with no
     * explicit link, they fall back to the same auto-classified default.
     */
    private function directPlanIdsFor(Collection $assignments, Collection $committeeAssignments, ?int $fiscalYear): Collection
    {
        $planIds = collect();

        $coveredLoadTypes = WorkDistributionPlan::forFiscalYear($fiscalYear)
            ->whereNotNull('load_source')
            ->pluck('load_source')
            ->unique();

        foreach ($assignments as $assignment) {
            if ($assignment->designation_id) {
                $explicitPlanIds = ($assignment->designation?->category?->workDistributionPlans()->pluck('work_distribution_plans.id') ?? collect())
                    ->merge($assignment->designation?->workDistributionPlans()->pluck('work_distribution_plans.id') ?? collect());

                if ($explicitPlanIds->isNotEmpty()) {
                    $planIds = $planIds->merge($explicitPlanIds);
                    continue;
                }
            } elseif ($coveredLoadTypes->contains($assignment->assignment_type)) {
                continue; // covered by a load_source-tagged framework plan, attached in generate() part (a)
            }

            $planIds->push($this->classifier->defaultPlanForLoadAssignment($assignment, $fiscalYear)->id);
        }

        if ($committeeAssignments->isNotEmpty()) {
            $explicitByCommittee = DB::table('faculty_committee_assignment_work_distribution_plan')
                ->whereIn('faculty_committee_assignment_id', $committeeAssignments->pluck('id'))
                ->get()
                ->groupBy('faculty_committee_assignment_id');

            foreach ($committeeAssignments as $committeeAssignment) {
                $linked = $explicitByCommittee->get($committeeAssignment->id);
                if ($linked && $linked->isNotEmpty()) {
                    $planIds = $planIds->merge($linked->pluck('work_distribution_plan_id'));
                    continue;
                }

                $planIds->push($this->classifier->defaultPlanForCommitteeAssignment($committeeAssignment, $fiscalYear)->id);
            }
        }

        return $planIds->unique()->values();
    }

    /**
     * Load assignments in academic terms overlapping the rating period
     * (a calendar semester overlaps parts of two school years); falls back
     * to the current school year when term dates are unavailable.
     */
    private function loadAssignmentsFor(User $user, ?IPCRRatingPeriod $period): Collection
    {
        $query = LoadAssignment::with(['subject', 'section', 'designation.category'])
            ->where('user_id', $user->id);

        $termIds = collect();
        if ($period?->start_date && $period?->end_date) {
            $termIds = AcademicTerm::where('start_date', '<=', $period->end_date)
                ->where('end_date', '>=', $period->start_date)
                ->pluck('id');
        }

        if ($termIds->isNotEmpty()) {
            $query->whereIn('academic_term_id', $termIds);
        } else {
            $currentSyId = SchoolYear::where('is_current', true)->value('id');
            $query->when($currentSyId, fn ($q) => $q->where('school_year_id', $currentSyId));
        }

        return $query->get();
    }

    /**
     * One line per load: teaching grouped per subject with its sections,
     * everything else by designation/description.
     */
    private function buildTarget(Collection $assignments, ?string $loadSource): ?string
    {
        $rows = $assignments->where('assignment_type', $loadSource);
        if ($rows->isEmpty()) {
            return null;
        }

        if ($loadSource === 'teaching') {
            $lines = $rows->groupBy('subject_id')->map(function ($group) {
                $first    = $group->first();
                $subject  = $first->subject?->name ?? $first->description ?? 'Teaching load';
                $sections = $group->map(fn ($a) => $a->section?->sectionname)->filter()->unique()->implode(', ');
                $units    = number_format($group->sum('load_units'), 2);

                return $subject
                    . ($sections ? " — {$sections}" : '')
                    . " ({$units} u)";
            })->values();
        } else {
            $lines = $rows->map(function ($a) {
                $label = $a->designation?->name ?? $a->description ?? ucfirst((string) $a->assignment_type);
                $units = number_format((float) $a->load_units, 2);

                return "{$label} ({$units} u)";
            })->unique()->values();
        }

        return $lines->isEmpty() ? null : $lines->map(fn ($l) => "• {$l}")->implode("\n");
    }
}
