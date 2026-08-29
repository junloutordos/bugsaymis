<?php

namespace App\Services\PerformanceManagement;

use App\Models\AgencyOutcome;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\PerformanceIndicator;
use App\Models\WorkDistributionPlan;

/**
 * Auto-classifies Faculty Loading assignments into Core or Support Function
 * Work Distribution Plans, per the approved rule:
 *
 *   - A LoadAssignment / FacultyCommitteeAssignment carrying an actual unit
 *     load (load_units > 0) defaults to a Core Functions WDP.
 *   - A LoadAssignment with no unit load, or a committee assignment with no
 *     load, defaults to a Support Functions WDP.
 *
 * Every distinct assignment gets its OWN WDP row — nothing is merged. A
 * faculty member teaching two subjects (e.g. Math 1 and Science 1) gets two
 * separate Core Functions rows on their IPCR, each individually rateable.
 * This mirrors an explicit WDP link 1:1 to a single assignment; it just
 * auto-generates and auto-labels the WDP instead of requiring CID/HR to tag
 * one by hand.
 *
 * This NEVER mutates an existing, already-tagged AgencyOutcome/WDP — each
 * auto-generated plan gets its own dedicated AgencyOutcome/PerformanceIndicator
 * row (via a marker "source" tag), so nothing here can leak into or alter
 * unrelated, manually-curated Work Distribution Plans.
 */
class WorkDistributionPlanClassifier
{
    public const CORE_FUNCTIONS = 'Core Functions';
    public const SUPPORT_FUNCTIONS = 'Support Functions';

    /**
     * Resolve the function type label for an assignment based on its unit load.
     */
    public function functionTypeFor(bool $hasUnitLoad): string
    {
        return $hasUnitLoad ? self::CORE_FUNCTIONS : self::SUPPORT_FUNCTIONS;
    }

    /**
     * Find or create the dedicated auto-generated WDP for one specific
     * LoadAssignment. Keyed 1:1 on the assignment's own id, so two
     * assignments (even identical subjects/units) never share a plan.
     */
    public function defaultPlanForLoadAssignment(LoadAssignment $assignment, ?int $fiscalYear = null): WorkDistributionPlan
    {
        return $this->defaultPlanFor(
            functionType: $this->functionTypeFor($assignment->hasUnitLoad()),
            fiscalYear: $fiscalYear,
            sourceType: LoadAssignment::class,
            sourceId: $assignment->id,
            label: $assignment->display_label,
        );
    }

    /**
     * Find or create the dedicated auto-generated WDP for one specific
     * FacultyCommitteeAssignment. Keyed 1:1 on the assignment's own id.
     */
    public function defaultPlanForCommitteeAssignment(FacultyCommitteeAssignment $assignment, ?int $fiscalYear = null): WorkDistributionPlan
    {
        return $this->defaultPlanFor(
            functionType: $this->functionTypeFor($assignment->hasUnitLoad()),
            fiscalYear: $fiscalYear,
            sourceType: FacultyCommitteeAssignment::class,
            sourceId: $assignment->id,
            label: $assignment->committee_name,
        );
    }

    /**
     * Find or create a single-assignment placeholder AgencyOutcome +
     * PerformanceIndicator + WorkDistributionPlan chain, uniquely keyed by
     * (function type, source model, source id) so it never collides with
     * another assignment's auto-generated plan or with any manually-tagged
     * outcome/plan elsewhere in the system.
     *
     * Evergreen — always stored with a NULL fiscal_year, regardless of which
     * period `generate()` happened to run under. An assignment's auto-
     * generated plan must be the exact same row every time it's resolved;
     * keying it to the caller's fiscal year would create a new duplicate
     * plan every time the operative year changed, permanently orphaning the
     * old one on any IPCR it was already attached to.
     */
    private function defaultPlanFor(string $functionType, ?int $fiscalYear, string $sourceType, int $sourceId, string $label): WorkDistributionPlan
    {
        $subOutcome = "{$sourceType}#{$sourceId}";

        $outcome = AgencyOutcome::firstOrCreate(
            [
                'outcome'       => $functionType,
                'sub_outcome'   => $subOutcome,
                'function_type' => $functionType,
                'fiscal_year'   => null,
            ]
        );

        $indicator = PerformanceIndicator::firstOrCreate(
            [
                'agency_outcome_id' => $outcome->id,
                'fiscal_year'       => null,
            ],
            [
                'description' => "Faculty Loading — {$label} (auto-generated)",
            ]
        );

        return WorkDistributionPlan::firstOrCreate(
            [
                'performance_indicator_id' => $indicator->id,
                'fiscal_year'              => null,
            ],
            [
                'success_indicator' => $label,
                'load_source'       => null,
            ]
        );
    }
}
