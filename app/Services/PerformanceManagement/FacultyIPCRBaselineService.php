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
 * linked to their committees, and any WDPs they are assigned to as
 * personnel — then personalizes each framework row's individual_target
 * from their actual load assignments (subjects, sections, units).
 */
class FacultyIPCRBaselineService
{
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
        $committeeIds = FacultyCommitteeAssignment::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('committee_id')
            ->pluck('committee_id');

        $committeePlanIds = $committeeIds->isEmpty() ? collect() : DB::table('committee_work_distribution_plan')
            ->whereIn('committee_id', $committeeIds)
            ->pluck('work_distribution_plan_id');

        // (c) Plans assigned to the teacher as personnel
        $personnelPlanIds = DB::table('plan_user')
            ->where('user_id', $user->id)
            ->pluck('work_distribution_plan_id');

        $attachIds = WorkDistributionPlan::whereIn(
                'id',
                $frameworkPlans->pluck('id')
                    ->merge($committeePlanIds)
                    ->merge($personnelPlanIds)
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
     * Load assignments in academic terms overlapping the rating period
     * (a calendar semester overlaps parts of two school years); falls back
     * to the current school year when term dates are unavailable.
     */
    private function loadAssignmentsFor(User $user, ?IPCRRatingPeriod $period): Collection
    {
        $query = LoadAssignment::with(['subject', 'section', 'designation'])
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
