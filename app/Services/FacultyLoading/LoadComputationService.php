<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SstPosition;

/**
 * LoadComputationService
 *
 * Implements all load unit rules from BOT Resolution No. 2025-05-80:
 *   - Full Load threshold = 18 units
 *   - Max administrative load = 15 units
 *   - Max research advisory load = 5 units
 *   - PHTR = (Annual Rate / 1,600) × 1.25
 *   - Overload Pay = PHTR × overload_hours × term_weeks
 */
class LoadComputationService
{
    // ── Constants (BOT Resolution No. 2025-05-80) ────────────────────────────

    public const FULL_LOAD_THRESHOLD   = 18.0;
    public const MAX_ADMIN_UNITS       = 15.0;
    public const MAX_RESEARCH_UNITS    = 5.0;
    public const PHTR_DIVISOR          = 1600;
    public const PHTR_MULTIPLIER       = 1.25;
    public const MAX_TEACHING_HRS_DAY  = 6.0;   // normal
    public const MAX_TEACHING_HRS_EXIGENCY = 8.0; // exigency

    // ── Load Computation ─────────────────────────────────────────────────────

    /**
     * Re-aggregate all assignment rows for a FacultyLoad record and persist
     * the computed totals + status.  Returns the refreshed model.
     */
    public function syncLoad(FacultyLoad $load): FacultyLoad
    {
        $assignments = LoadAssignment::where('faculty_load_id', $load->id)->get();

        $teaching    = $assignments->where('assignment_type', 'teaching')   ->sum('load_units');
        $research    = $assignments->where('assignment_type', 'research')   ->sum('load_units');
        $admin       = $assignments->where('assignment_type', 'admin')      ->sum('load_units');
        $cocurr      = $assignments->where('assignment_type', 'cocurricular')->sum('load_units');
        $committee   = $assignments->where('assignment_type', 'committee')  ->sum('load_units');

        $total  = $teaching + $research + $admin + $cocurr + $committee;
        $status = $this->getLoadStatus($total, (float) $load->full_load_threshold);

        $load->update([
            'teaching_units'     => round($teaching,  2),
            'research_units'     => round($research,  2),
            'admin_units'        => round($admin,     2),
            'cocurricular_units' => round($cocurr,    2),
            'committee_units'    => round($committee, 2),
            'total_units'        => round($total,     2),
            'load_status'        => $status,
        ]);

        return $load->fresh();
    }

    /**
     * Classify a total load against the threshold.
     */
    public function getLoadStatus(float $total, float $threshold = self::FULL_LOAD_THRESHOLD): string
    {
        if ($total < $threshold) {
            return 'underload';
        }
        if ($total == $threshold) {
            return 'full_load';
        }
        return 'overload';
    }

    /**
     * Units by which a load exceeds the full-load threshold.
     */
    public function getOverloadUnits(float $total, float $threshold = self::FULL_LOAD_THRESHOLD): float
    {
        return max(0.0, round($total - $threshold, 2));
    }

    /**
     * Units short of full load (useful for underload warnings).
     */
    public function getUnderloadUnits(float $total, float $threshold = self::FULL_LOAD_THRESHOLD): float
    {
        return max(0.0, round($threshold - $total, 2));
    }

    // ── PHTR & Overload Pay ──────────────────────────────────────────────────

    /**
     * Per Hour Teaching Rate.
     * PHTR = (Annual Rate / 1,600) × 1.25
     */
    public function computePHTR(float $annualRate): float
    {
        if ($annualRate <= 0) {
            return 0.0;
        }
        return round(($annualRate / self::PHTR_DIVISOR) * self::PHTR_MULTIPLIER, 4);
    }

    /**
     * Total overload pay for one semester.
     * Pay = PHTR × overload contact hours per week × number of weeks in term
     */
    public function computeOverloadPay(float $phtr, float $overloadHours, int $termWeeks = 18): float
    {
        if ($phtr <= 0 || $overloadHours <= 0 || $termWeeks <= 0) {
            return 0.0;
        }
        return round($phtr * $overloadHours * $termWeeks, 2);
    }

    /**
     * Full overload computation from raw inputs.
     * Returns an array with all intermediate values for storage and display.
     */
    public function computeOverloadBreakdown(
        float $annualRate,
        float $overloadUnits,
        float $overloadHoursPerWeek,
        int   $termWeeks = 18
    ): array {
        $phtr            = $this->computePHTR($annualRate);
        $totalOverloadPay = $this->computeOverloadPay($phtr, $overloadHoursPerWeek, $termWeeks);

        return [
            'annual_rate'        => $annualRate,
            'phtr'               => $phtr,
            'overload_units'     => $overloadUnits,
            'overload_hours'     => $overloadHoursPerWeek,
            'term_weeks'         => $termWeeks,
            'total_overload_pay' => $totalOverloadPay,
        ];
    }

    // ── Limit Validations ────────────────────────────────────────────────────

    /**
     * Validate administrative load.
     * Returns ['valid' => bool, 'message' => string, 'current' => float, 'max' => float]
     */
    public function validateAdminLoad(float $adminUnits): array
    {
        $valid = $adminUnits <= self::MAX_ADMIN_UNITS;
        return [
            'valid'   => $valid,
            'current' => $adminUnits,
            'max'     => self::MAX_ADMIN_UNITS,
            'message' => $valid
                ? 'Administrative load is within the allowed limit.'
                : "Administrative load ({$adminUnits} units) exceeds the maximum of " . self::MAX_ADMIN_UNITS . ' units.',
        ];
    }

    /**
     * Validate research advisory load.
     */
    public function validateResearchLoad(float $researchUnits): array
    {
        $valid = $researchUnits <= self::MAX_RESEARCH_UNITS;
        return [
            'valid'   => $valid,
            'current' => $researchUnits,
            'max'     => self::MAX_RESEARCH_UNITS,
            'message' => $valid
                ? 'Research advisory load is within the allowed limit.'
                : "Research load ({$researchUnits} units) exceeds the maximum of " . self::MAX_RESEARCH_UNITS . ' units.',
        ];
    }

    /**
     * Validate teaching hours per day.
     * Returns warning level: 'ok' | 'warning' (>6h) | 'exceeded' (>8h exigency)
     */
    public function validateDailyTeachingHours(float $dailyHours): array
    {
        if ($dailyHours <= self::MAX_TEACHING_HRS_DAY) {
            return ['level' => 'ok',       'hours' => $dailyHours, 'message' => 'Within normal daily limit.'];
        }
        if ($dailyHours <= self::MAX_TEACHING_HRS_EXIGENCY) {
            return ['level' => 'warning',  'hours' => $dailyHours, 'message' => "Daily teaching hours ({$dailyHours}h) exceed the normal 6-hour limit. Exigency approval may be required."];
        }
        return ['level' => 'exceeded', 'hours' => $dailyHours, 'message' => "Daily teaching hours ({$dailyHours}h) exceed the maximum 8-hour exigency limit."];
    }

    /**
     * Run all limit validations on a FacultyLoad and return a summary.
     *
     * @param FacultyLoad    $load      The faculty load record for the term.
     * @param SstPosition|null $position Optional SST position for min-teaching-units check.
     */
    public function validateAll(FacultyLoad $load, ?SstPosition $position = null): array
    {
        $adminResult    = $this->validateAdminLoad((float) $load->admin_units);
        $researchResult = $this->validateResearchLoad((float) $load->research_units);
        $overloadUnits  = $this->getOverloadUnits((float) $load->total_units, (float) $load->full_load_threshold);

        $warnings = [];
        $errors   = [];

        if (! $adminResult['valid'])    $errors[]   = $adminResult['message'];
        if (! $researchResult['valid']) $errors[]   = $researchResult['message'];

        if ($overloadUnits > 0 && ! $load->overload_approved) {
            $warnings[] = "Faculty has an overload of {$overloadUnits} units pending approval.";
        }
        if ($load->isUnderload()) {
            $underload = $this->getUnderloadUnits((float) $load->total_units, (float) $load->full_load_threshold);
            $warnings[] = "Faculty is underloaded by {$underload} units.";
        }

        // ── Position-based minimum teaching units ────────────────────────────
        $positionCheck = null;
        if ($position) {
            $positionCheck = $this->validatePositionLoad((float) $load->teaching_units, $position);
            if (! $positionCheck['valid']) {
                $warnings[] = $positionCheck['message'];
            }
        }

        // ── Committee compliance ─────────────────────────────────────────────
        $committeeCheck = $this->checkCommitteeCompliance(
            $load->user_id,
            $load->academic_term_id
        );
        if (! $committeeCheck['compliant']) {
            $warnings[] = $committeeCheck['message'];
        }

        return [
            'valid'           => empty($errors),
            'errors'          => $errors,
            'warnings'        => $warnings,
            'admin_check'     => $adminResult,
            'research_check'  => $researchResult,
            'position_check'  => $positionCheck,
            'committee_check' => $committeeCheck,
            'load_status'     => $load->load_status,
            'total_units'     => (float) $load->total_units,
            'overload_units'  => $overloadUnits,
        ];
    }

    // ── Position-Based Validation (SST I–V) ──────────────────────────────────

    /**
     * Validate that teaching units meet the SST position's minimum requirement.
     *
     * Per PSHS practice, senior SST levels (SST-III to SST-V) still have a
     * minimum classroom instruction requirement even when carrying heavy
     * administrative or research loads.
     *
     * @return array{valid: bool, current: float, min: float, position: string, message: string}
     */
    public function validatePositionLoad(float $teachingUnits, SstPosition $position): array
    {
        $min   = (float) $position->min_teaching_units;
        $valid = $teachingUnits >= $min;

        return [
            'valid'    => $valid,
            'current'  => $teachingUnits,
            'min'      => $min,
            'position' => $position->code,
            'message'  => $valid
                ? "{$position->code}: Teaching load ({$teachingUnits} units) meets the minimum requirement of {$min} units."
                : "{$position->code}: Teaching load ({$teachingUnits} units) is below the minimum of {$min} units required for this position.",
        ];
    }

    // ── Committee Compliance ─────────────────────────────────────────────────

    /**
     * Check that a faculty member has at least one active committee assignment
     * for the given academic term.
     *
     * Per BOT Resolution No. 2025-05-80, every faculty member must hold
     * membership in at least one institutional committee per term.
     * Chairpersonships are also tracked for compliance reporting.
     *
     * @return array{compliant: bool, count: int, has_chair: bool, message: string}
     */
    public function checkCommitteeCompliance(int $userId, int $termId): array
    {
        $assignments = FacultyCommitteeAssignment::where('user_id', $userId)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->get();

        $count    = $assignments->count();
        $hasChair = $assignments->whereIn('role', ['chairperson', 'co_chair'])->isNotEmpty();

        if ($count === 0) {
            return [
                'compliant' => false,
                'count'     => 0,
                'has_chair' => false,
                'message'   => 'Faculty has no active committee assignment for this term. '
                             . 'Per BOT Resolution No. 2025-05-80, at least one committee membership is required.',
            ];
        }

        return [
            'compliant' => true,
            'count'     => $count,
            'has_chair' => $hasChair,
            'message'   => "Committee compliance met: {$count} committee assignment(s)"
                         . ($hasChair ? ', including a chairpersonship.' : '.'),
        ];
    }
}
