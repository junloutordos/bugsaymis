<?php

namespace App\Services\PerformanceManagement;

use App\Models\Accomplishment;
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
 * assignments / committee assignments — then personalizes each row's
 * individual_target from their actual load assignments.
 *
 * A LoadAssignment backed by a Designation (e.g. ACIDAA, Prefect of
 * Discipline) inherits the union of whatever WDPs are tagged on that
 * Designation's own Category "mother record" AND whatever is tagged on the
 * Designation itself — the category tag is a shared default for every
 * designation under it, a designation's own tag is additional on top of
 * that.
 *
 * A LoadAssignment with no designation and assignment_type `teaching` is
 * grouped by subject (multiple sections of the SAME subject merge into one
 * distinct teaching load; two different subjects — even similarly named
 * electives at different grade levels — never merge). Each distinct
 * teaching load covered by one or more `load_source='teaching'`-tagged
 * plans (the Designations module's Teaching Load tab) gets one
 * independently-rateable materialized row per (subject, tagged plan) —
 * its own accomplishment/MOV/rating, mirroring the tagged plan's text.
 *
 * Any other non-designation load (research/admin/etc.) is instead covered
 * by a framework plan explicitly tagged with a matching `load_source`, one
 * shared row personalized to list every such assignment as its own bullet.
 *
 * Whatever isn't covered by any of the above falls back to its own
 * dedicated, auto-classified Core/Support Functions WDP — nothing is
 * merged there, so an untagged faculty member teaching two subjects gets
 * two separate rows.
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

        [$rawTeaching, $otherAssignments] = $assignments->partition(
            fn ($a) => ! $a->designation_id && $a->assignment_type === 'teaching'
        );

        // (a) Framework plans matching the teacher's OTHER load types —
        // teaching is handled separately below, per distinct subject.
        // Evergreen "mother record" tags, not fiscal-year-scoped.
        $loadTypes = $otherAssignments->pluck('assignment_type')->filter()
            ->reject(fn ($t) => $t === 'teaching')->unique()->values();
        $frameworkPlans = WorkDistributionPlan::whereIn('load_source', $loadTypes)->get();

        // (a2) Teaching Load: one independent, separately-rateable row per
        // distinct subject (sections of the same subject merged) per
        // tagged plan — or the same per-subject auto-classified fallback
        // when nothing's tagged.
        [$teachingPlanIds, $teachingTargets] = $this->materializedTeachingPlans($rawTeaching, $period?->year);

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

        // (d) Plans linked directly to the teacher's own designation-backed
        // load assignments / committee assignments. Any assignment with no
        // explicit link of its own falls back to the Core/Support default.
        [$directPlanIds, $directTargets] = $this->directPlanIdsFor($otherAssignments, $committeeAssignments, $period?->year);

        // Committee- and personnel-linked plans stay fiscal-year-scoped
        // (pre-existing, unchanged behavior). Framework (load_source),
        // Teaching Load materialized, and direct (Designations-module
        // Category/Designation) tags are evergreen "mother record" tags —
        // they don't need to match the IPCR's fiscal year to apply.
        $fiscalScopedIds = WorkDistributionPlan::whereIn('id', $committeePlanIds->merge($personnelPlanIds)->unique())
            ->forFiscalYear($period?->year)
            ->pluck('id');

        $attachIds = $frameworkPlans->pluck('id')
            ->merge($teachingPlanIds)
            ->merge($directPlanIds)
            ->merge($fiscalScopedIds)
            ->unique()
            ->all();

        $before = $ipcr->plans()->pluck('work_distribution_plans.id');
        $ipcr->plans()->syncWithoutDetaching($attachIds);
        $this->detachSupersededFallbackPlans($ipcr, $assignments, $committeeAssignments, $attachIds);

        // Personalize framework rows (non-teaching load types) that don't
        // have a target yet
        $existingTargets = DB::table('employee_ipcrs_plan')
            ->where('ipcr_id', $ipcr->id)
            ->pluck('individual_target', 'plan_id');

        $personalized = 0;
        foreach ($frameworkPlans as $plan) {
            if (filled($existingTargets[$plan->id] ?? null)) {
                continue; // never clobber a target the teacher already wrote
            }

            $target = $this->buildTarget($otherAssignments, $plan->load_source);
            if ($target === null) {
                continue;
            }

            $ipcr->plans()->updateExistingPivot($plan->id, ['individual_target' => $target]);
            $personalized++;
        }

        // Personalize materialized Teaching Load rows
        foreach ($teachingTargets as $planId => $target) {
            if (filled($existingTargets[$planId] ?? null)) {
                continue;
            }

            $ipcr->plans()->updateExistingPivot($planId, ['individual_target' => $target]);
            $personalized++;
        }

        // Personalize direct rows (both explicitly category/designation-
        // tagged plans and per-assignment auto-classified fallbacks) with
        // the assignment's real label. Without this, the Sub-Outcome column
        // falls back to the raw technical identity marker (e.g.
        // "LoadAssignment#621") for fallback rows, or renders blank for
        // explicitly-tagged plans (their sub_outcome is intentionally NULL).
        foreach ($directTargets as $planId => $target) {
            if (filled($existingTargets[$planId] ?? null)) {
                continue;
            }

            $ipcr->plans()->updateExistingPivot($planId, ['individual_target' => $target]);
            $personalized++;
        }

        return [
            'attached'     => count(array_diff($attachIds, $before->all())),
            'personalized' => $personalized,
        ];
    }

    /**
     * Resolve Teaching Load plan ids for raw (non-designation) teaching
     * assignments, grouped by subject — multiple sections of the SAME
     * subject merge into one distinct teaching load; two different
     * subjects (even similarly-named electives at different grade levels,
     * distinct Subject catalog records) never merge.
     *
     * Covered by one or more `load_source='teaching'`-tagged plans: one
     * independently-rateable materialized row per (subject, tagged plan).
     * Not covered: the same per-subject auto-classified fallback as any
     * other unlinked assignment.
     *
     * @return array{0: Collection, 1: array<int, string>} plan ids, and a
     *         map of materialized plan id => its personalized target line
     */
    private function materializedTeachingPlans(Collection $rawTeaching, ?int $fiscalYear): array
    {
        $planIds = collect();
        $targets = [];

        if ($rawTeaching->isEmpty()) {
            return [$planIds, $targets];
        }

        $taggedPlans = WorkDistributionPlan::where('load_source', 'teaching')->get();
        $bySubject = $rawTeaching->groupBy(fn ($a) => $a->subject_id ?? ('desc:' . $a->description));

        foreach ($bySubject as $group) {
            if ($taggedPlans->isNotEmpty()) {
                $line = $this->buildTeachingLine($group);

                foreach ($taggedPlans as $taggedPlan) {
                    $clone = $this->classifier->materializedTeachingPlanFor($group, $taggedPlan);
                    $planIds->push($clone->id);
                    $targets[$clone->id] = $line;
                }
                continue;
            }

            $representative = $group->sortBy('id')->first();
            $planIds->push($this->classifier->defaultPlanForLoadAssignment($representative, $fiscalYear)->id);
        }

        return [$planIds, $targets];
    }

    /**
     * One line for a group of LoadAssignment rows sharing the same
     * subject: subject name, every distinct section merged, total units
     * summed.
     */
    private function buildTeachingLine(Collection $group): string
    {
        $first    = $group->first();
        $subject  = $first->subject?->name ?? $first->description ?? 'Teaching load';
        $sections = $group->map(fn ($a) => $a->section?->sectionname)->filter()->unique()->implode(', ');
        $units    = number_format($group->sum('load_units'), 2);

        return $subject . ($sections ? " — {$sections}" : '') . " ({$units} u)";
    }

    /**
     * Detach auto-generated fallback plans that no longer belong.
     *
     * `syncWithoutDetaching()` only ever attaches — a plan that was
     * auto-generated for a LoadAssignment/FacultyCommitteeAssignment (marked
     * via its AgencyOutcome.sub_outcome, e.g. "LoadAssignment#117", or a
     * materialized Teaching Load row, "LoadAssignment#117@536") can end up
     * permanently stuck on the IPCR once a real tag supersedes it, since
     * nothing else ever removes it. This walks the IPCR's currently attached
     * plans and detaches any auto-generated one whose source assignment is
     * still one of this teacher's CURRENT assignments but is no longer part
     * of this run's resolved $attachIds (i.e. a category/designation/
     * teaching-load tag now covers it instead, or covers it via a different
     * tagged plan).
     *
     * Never detaches a plan with real Accomplishment records logged against
     * it — that's rated work, left for manual review rather than silently
     * removed.
     */
    private function detachSupersededFallbackPlans(EmployeeIPCR $ipcr, Collection $assignments, Collection $committeeAssignments, array $attachIds): void
    {
        $currentSourceMarkers = $assignments->pluck('id')->map(fn ($id) => LoadAssignment::class . '#' . $id)
            ->merge($committeeAssignments->pluck('id')->map(fn ($id) => FacultyCommitteeAssignment::class . '#' . $id));

        if ($currentSourceMarkers->isEmpty()) {
            return;
        }

        $staleIds = $ipcr->plans()
            ->whereNotIn('work_distribution_plans.id', $attachIds)
            ->whereHas('performanceIndicator.agencyOutcome', function ($q) use ($currentSourceMarkers) {
                $q->where(function ($q2) use ($currentSourceMarkers) {
                    foreach ($currentSourceMarkers as $marker) {
                        // Exact match (plain fallback) or "<marker>@..."
                        // prefix (a materialized per-tag copy of the same
                        // assignment/subject group). Markers are fully-
                        // qualified class names containing "\" — MySQL's
                        // LIKE treats "\" as an escape character, so it
                        // MUST be escaped (along with % and _) before being
                        // used as a LIKE pattern, or the match silently
                        // finds nothing.
                        $escapedMarker = addcslashes($marker, '\\%_');
                        $q2->orWhere('sub_outcome', $marker)
                            ->orWhere('sub_outcome', 'like', $escapedMarker . '@%');
                    }
                });
            })
            ->get()
            ->filter(function ($plan) use ($ipcr) {
                $pivotId = DB::table('employee_ipcrs_plan')
                    ->where('ipcr_id', $ipcr->id)->where('plan_id', $plan->id)->value('id');

                return ! $pivotId || ! Accomplishment::where('ipcr_plan_id', $pivotId)->exists();
            })
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            $ipcr->plans()->detach($staleIds);
        }
    }

    /**
     * Resolve WDP ids linked directly to this teacher's own designation-
     * backed load assignments and committee assignments. (Raw, non-
     * designation teaching loads are handled separately — see
     * materializedTeachingPlans() — so $assignments here never includes
     * them.)
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
     * A raw LoadAssignment with no designation and a non-teaching type is
     * instead covered by a "framework" plan — one explicitly tagged with
     * `load_source` matching its assignment_type, attached globally in
     * generate() part (a) and personalized in buildTarget().
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
     *
     * @return array{0: Collection, 1: array<int, string>} plan ids, and a
     *         map of plan id => its personalized target line (designation
     *         display label or committee name), covering both the
     *         explicitly-tagged shared plans (which carry no per-teacher
     *         sub_outcome identity of their own — without this the
     *         Sub-Outcome cell would render blank) and the per-assignment
     *         auto-classified fallback plans.
     */
    private function directPlanIdsFor(Collection $assignments, Collection $committeeAssignments, ?int $fiscalYear): array
    {
        $planIds = collect();
        $targets = [];

        $coveredLoadTypes = WorkDistributionPlan::whereNotNull('load_source')
            ->pluck('load_source')
            ->unique();

        foreach ($assignments as $assignment) {
            if ($assignment->designation_id) {
                $explicitPlanIds = ($assignment->designation?->category?->workDistributionPlans()->pluck('work_distribution_plans.id') ?? collect())
                    ->merge($assignment->designation?->workDistributionPlans()->pluck('work_distribution_plans.id') ?? collect());

                if ($explicitPlanIds->isNotEmpty()) {
                    $planIds = $planIds->merge($explicitPlanIds);

                    $units = number_format((float) $assignment->load_units, 2);
                    $line  = "{$assignment->display_label} ({$units} u)";
                    foreach ($explicitPlanIds as $pid) {
                        // A category/designation tag can attach the same
                        // shared plan for more than one of this teacher's
                        // own assignments — append rather than clobber.
                        $targets[$pid] = isset($targets[$pid]) ? $targets[$pid] . "\n" . $line : $line;
                    }
                    continue;
                }
            } elseif ($coveredLoadTypes->contains($assignment->assignment_type)) {
                continue; // covered by a load_source-tagged framework plan, attached in generate() part (a)
            }

            $plan = $this->classifier->defaultPlanForLoadAssignment($assignment, $fiscalYear);
            $planIds->push($plan->id);
            $units = number_format((float) $assignment->load_units, 2);
            $targets[$plan->id] = "{$assignment->display_label} ({$units} u)";
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

                $plan = $this->classifier->defaultPlanForCommitteeAssignment($committeeAssignment, $fiscalYear);
                $planIds->push($plan->id);
                $targets[$plan->id] = $committeeAssignment->committee_name;
            }
        }

        return [$planIds->unique()->values(), $targets];
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
     * One line per non-teaching load, by designation name/description.
     * (Teaching loads are personalized separately, per distinct subject —
     * see materializedTeachingPlans() / buildTeachingLine().)
     */
    private function buildTarget(Collection $assignments, ?string $loadSource): ?string
    {
        $rows = $assignments->where('assignment_type', $loadSource);
        if ($rows->isEmpty()) {
            return null;
        }

        $lines = $rows->map(function ($a) {
            $label = $a->designation?->name ?? $a->description ?? ucfirst((string) $a->assignment_type);
            $units = number_format((float) $a->load_units, 2);

            return "{$label} ({$units} u)";
        })->unique()->values();

        return $lines->isEmpty() ? null : $lines->map(fn ($l) => "• {$l}")->implode("\n");
    }
}
