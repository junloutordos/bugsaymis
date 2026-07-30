<?php

namespace App\Services\ClassRecord;

/**
 * Pure grade computation engine for the PSHS-Caraga Class Record module.
 *
 * No database calls — all data is passed in as parameters so this service
 * is fully unit-testable without hitting the database.
 */
class GradeComputationService
{
    // ── Method 1 ─────────────────────────────────────────────────────────────

    /**
     * Compute the aggregate score for one grading category.
     *
     * @param  array<int|float|null> $scores     Raw scores (null = not entered → treated as 0)
     * @param  array<int|float>      $maxScores  Maximum possible scores per assessment
     * @param  float                 $weight     Category weight (e.g. 0.70 for 70%)
     * @return array{total: float, maxTotal: float, percentage: float, weightedPercentage: float}
     */
    public function computeCategoryScore(array $scores, array $maxScores, float $weight): array
    {
        $total    = array_sum(array_map(fn ($s) => $s ?? 0, $scores));
        $maxTotal = array_sum($maxScores);

        $percentage         = $maxTotal > 0 ? $total / $maxTotal : 0.0;
        $weightedPercentage = $percentage * $weight;

        return compact('total', 'maxTotal', 'percentage', 'weightedPercentage');
    }

    // ── Method 4 (declared before Method 2 that calls it) ────────────────────

    /**
     * Look up the grade equivalent for a given percentage score.
     *
     * @param  float $percentageScore  TW% × 100 (0–100)
     * @param  iterable $stanineLookup Collection or array of stanine rows
     *         each row must have: percentage, grade_equivalent, adjectival_equivalent
     * @return array{gradeEquivalent: float, adjectivalEquivalent: string}
     */
    public function lookupGradeEquivalent(float $percentageScore, iterable $stanineLookup): array
    {
        // Convert to integer, clamped to the table range 39–100
        $pct = max(39, min(100, (int) floor($percentageScore)));

        foreach ($stanineLookup as $row) {
            $rowPct = is_array($row) ? (int) $row['percentage'] : (int) $row->percentage;
            if ($rowPct === $pct) {
                return [
                    'gradeEquivalent'     => is_array($row) ? (float) $row['grade_equivalent'] : (float) $row->grade_equivalent,
                    'adjectivalEquivalent'=> is_array($row) ? $row['adjectival_equivalent'] : $row->adjectival_equivalent,
                ];
            }
        }

        // Fallback — should not happen if lookup is complete (39–100)
        return ['gradeEquivalent' => 4.510, 'adjectivalEquivalent' => 'Failed'];
    }

    // ── Method 2 ─────────────────────────────────────────────────────────────

    /**
     * Compute the full quarter grade for one student given their category results.
     *
     * @param  array $categoryResults  [ ['categoryCode' => 'AA', 'percentage' => 0.85, 'weight' => 0.70], ... ]
     * @param  iterable $stanineLookup
     * @return array{
     *   totalWeightedPercentage: float,
     *   percentageScore: float,
     *   gradeEquivalent: float,
     *   adjectivalEquivalent: string
     * }
     */
    public function computeStudentQuarterGrade(array $categoryResults, iterable $stanineLookup): array
    {
        $totalWeightedPercentage = array_sum(
            array_map(fn ($r) => ($r['percentage'] ?? 0.0) * ($r['weight'] ?? 0.0), $categoryResults)
        );

        $percentageScore = round($totalWeightedPercentage * 100, 2);

        $lookup = $this->lookupGradeEquivalent($percentageScore, $stanineLookup);

        return [
            'totalWeightedPercentage' => $totalWeightedPercentage,
            'percentageScore'         => $percentageScore,
            'gradeEquivalent'         => $lookup['gradeEquivalent'],
            'adjectivalEquivalent'    => $lookup['adjectivalEquivalent'],
        ];
    }

    // ── Method 3 ─────────────────────────────────────────────────────────────

    /**
     * Compute the running (cumulative) grade after applying the rolling formula.
     *
     * Q1: finalGrade = currentQuarterGE
     * Q2–Q4: finalGrade = floor3((currentQGE × 2/3) + (previousRunning × 1/3))
     *
     * "floor3" = truncate to 3 decimal places (floor, NOT round) to match Excel behaviour.
     *
     * @param  float      $currentQuarterGE
     * @param  float|null $previousRunningGrade  null for Q1
     * @param  int        $quarter               1–4
     * @param  iterable   $stanineLookup
     * @return array{finalGrade: float, finalGradeGE: float, runningAdjectival: string}
     */
    public function computeRunningGrade(
        float $currentQuarterGE,
        ?float $previousRunningGrade,
        int $quarter,
        iterable $stanineLookup
    ): array {
        if ($quarter === 1) {
            $finalGrade = $currentQuarterGE;
        } elseif ($previousRunningGrade === null) {
            // Q1 was never computed — use 5.0 (Failed) as default previous grade
            $raw        = ($currentQuarterGE * (2 / 3)) + (5.0 * (1 / 3));
            $finalGrade = $this->floorToDecimals($raw, 3);
        } else {
            $raw        = ($currentQuarterGE * (2 / 3)) + ($previousRunningGrade * (1 / 3));
            $finalGrade = $this->floorToDecimals($raw, 3);
        }

        // Convert the running GE back to a percentage to look up the adjectival
        // We reverse-look-up: find the stanine row where grade_equivalent matches closest.
        // Simpler: derive adjectival by doing a forward lookup from the GE itself.
        $adjectival = $this->lookupAdjectivalByGE($finalGrade, $stanineLookup);

        return [
            'finalGrade'       => $finalGrade,
            'finalGradeGE'     => $finalGrade,        // GE is the final grade for class records
            'runningAdjectival'=> $adjectival,
        ];
    }

    // ── Method 5 ─────────────────────────────────────────────────────────────

    /**
     * Orchestrator: compute all derived values for every student in a quarter.
     *
     * @param  array $data {
     *   quarter: int,
     *   gradingOption: { categories: [{ id, code, weight, assessments: [{ id, maxScore }] }] },
     *   students: [{ id, scores: { [assessmentId]: float|null } }],
     *   stanineLookup: iterable,
     *   previousQuarterGrades: { [studentId]: float|null }
     * }
     * @return array{ students: array }
     */
    public function computeFullClassRecord(array $data): array
    {
        $quarter               = $data['quarter'];
        $categories            = $data['gradingOption']['categories'] ?? [];
        $students              = $data['students'] ?? [];
        $stanineLookup         = $data['stanineLookup'] ?? [];
        $previousQuarterGrades = $data['previousQuarterGrades'] ?? [];

        $results = [];

        foreach ($students as $student) {
            $studentId     = $student['id'];
            $scores        = $student['scores'] ?? [];
            $categoryRows  = [];

            foreach ($categories as $category) {
                $catId       = $category['id'];
                $catCode     = $category['code'];
                $weight      = (float) $category['weight'];
                $assessments = $category['assessments'] ?? [];

                $rawScores  = [];
                $maxScores  = [];
                foreach ($assessments as $assessment) {
                    $aid         = $assessment['id'];
                    $rawScores[] = $scores[$aid] ?? null;
                    $maxScores[] = (float) $assessment['maxScore'];
                }

                $catResult = $this->computeCategoryScore($rawScores, $maxScores, $weight);
                $categoryRows[] = array_merge($catResult, [
                    'categoryId'   => $catId,
                    'categoryCode' => $catCode,
                    'weight'       => $weight,
                ]);
            }

            $quarterGrade = $this->computeStudentQuarterGrade($categoryRows, $stanineLookup);

            $prevGrade    = $previousQuarterGrades[$studentId] ?? null;
            $running      = $this->computeRunningGrade(
                $quarterGrade['gradeEquivalent'],
                is_null($prevGrade) ? null : (float) $prevGrade,
                $quarter,
                $stanineLookup
            );

            $results[] = [
                'studentId'               => $studentId,
                'categories'              => $categoryRows,
                'totalWeightedPercentage' => $quarterGrade['totalWeightedPercentage'],
                'percentageScore'         => $quarterGrade['percentageScore'],
                'gradeEquivalent'         => $quarterGrade['gradeEquivalent'],
                'adjectivalEquivalent'    => $quarterGrade['adjectivalEquivalent'],
                'runningGrade'            => $running['finalGrade'],
                'runningGradeGE'          => $running['finalGradeGE'],
                'runningAdjectival'       => $running['runningAdjectival'],
            ];
        }

        return ['students' => $results];
    }

    // ── Method 6 ─────────────────────────────────────────────────────────────

    /**
     * Compute the final annual grade from 4 quarterly GEs.
     *
     * Formula: simple average of all 4 quarterly GEs, rounded to 3 decimal places.
     * Confirm this formula with the CID Chief / Registrar before shipping.
     *
     * @param  float $q1GE  Quarter 1 grade equivalent
     * @param  float $q2GE  Quarter 2 grade equivalent
     * @param  float $q3GE  Quarter 3 grade equivalent
     * @param  float $q4GE  Quarter 4 grade equivalent
     * @param  iterable $stanineLookup
     * @return array{ finalGE: float, adjectivalEquivalent: string }
     */
    public function computeFinalGrade(
        float $q1GE,
        float $q2GE,
        float $q3GE,
        float $q4GE,
        iterable $stanineLookup,
    ): array {
        $finalGE    = round(($q1GE + $q2GE + $q3GE + $q4GE) / 4, 3);
        $adjectival = $this->lookupAdjectivalByGE($finalGE, $stanineLookup);

        return [
            'finalGE'              => $finalGE,
            'adjectivalEquivalent' => $adjectival,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Truncate (floor) to N decimal places — matches Excel FLOOR behaviour.
     * e.g. floorToDecimals(1.6667, 3) → 1.666 (not 1.667)
     */
    private function floorToDecimals(float $value, int $decimals): float
    {
        $factor = 10 ** $decimals;
        return floor($value * $factor) / $factor;
    }

    /**
     * Given a GE value, find the matching adjectival by reverse-scanning the lookup table.
     * Returns the adjectival of the nearest matching row (exact match on grade_equivalent).
     */
    private function lookupAdjectivalByGE(float $ge, iterable $stanineLookup): string
    {
        $ge = round($ge, 3);
        foreach ($stanineLookup as $row) {
            $rowGe = round(is_array($row) ? (float) $row['grade_equivalent'] : (float) $row->grade_equivalent, 3);
            if ($rowGe === $ge) {
                return is_array($row) ? $row['adjectival_equivalent'] : $row->adjectival_equivalent;
            }
        }
        // If no exact match, return based on known ranges
        if ($ge <= 1.140) return 'Excellent';
        if ($ge <= 1.600) return 'Very Good';
        if ($ge <= 2.100) return 'Good';
        if ($ge <= 2.600) return 'Satisfactory';
        if ($ge <= 3.379) return 'Fair';
        if ($ge <= 4.402) return 'Failed on Condition';
        return 'Failed';
    }

    // ── Compliance-mode methods ─────────────────────────────────────────────
    //
    // For subjects graded by compliance (e.g. Values Education) rather than
    // numeric score — a "score" is really a checkbox (1 = complied,
    // 0 = did not), and the output is a completion percentage + a
    // qualitative remark instead of a percentage/GE/adjectival. The category
    // weighting math is identical to the numeric path (computeCategoryScore
    // is reused as-is); only what happens AFTER the weighted sum differs.

    /**
     * Compute one student's quarter-level compliance result: the same
     * weighted-sum math as computeStudentQuarterGrade(), but the output is a
     * compliance percentage + a pass/fail remark (using the option's own
     * configurable threshold and label text) instead of a GE/adjectival.
     *
     * @param  array  $categoryResults  [ ['percentage' => 0.85, 'weight' => 0.70], ... ] — same shape as the numeric path
     * @param  float  $passThreshold    0–1, e.g. 0.75 for "75% of activities completed"
     * @param  string $passLabel        e.g. "Completed"
     * @param  string $failLabel        e.g. "Not Completed"
     * @return array{compliancePercentage: float, remark: string, passed: bool}
     */
    public function computeComplianceCategory(array $categoryResults, float $passThreshold, string $passLabel, string $failLabel): array
    {
        $totalWeightedPercentage = array_sum(
            array_map(fn ($r) => ($r['percentage'] ?? 0.0) * ($r['weight'] ?? 0.0), $categoryResults)
        );

        $compliancePercentage = round($totalWeightedPercentage * 100, 2);
        $passed = $totalWeightedPercentage >= $passThreshold;

        return [
            'compliancePercentage' => $compliancePercentage,
            'remark'               => $passed ? $passLabel : $failLabel,
            'passed'               => $passed,
        ];
    }

    /**
     * Orchestrator for compliance mode: same per-student/per-category
     * weighted-sum pipeline as computeFullClassRecord(), but ending in a
     * compliance percentage + remark for every student instead of a
     * percentage/GE/adjectival. "Assessments" here are compliance checkboxes
     * — a score of 1 = complied, 0/null = did not (same convention already
     * used by the ILA compliance-to-score conversion elsewhere in this
     * module), so computeCategoryScore's existing null-as-zero handling
     * applies unchanged.
     *
     * @param  array $data {
     *   gradingOption: { categories: [{ id, code, weight, assessments: [{ id, maxScore }] }] },
     *   students: [{ id, scores: { [assessmentId]: float|null } }],
     *   passThreshold: float, passLabel: string, failLabel: string,
     * }
     * @return array{ students: array }
     */
    public function computeFullComplianceClassRecord(array $data): array
    {
        $categories    = $data['gradingOption']['categories'] ?? [];
        $students      = $data['students'] ?? [];
        $passThreshold = (float) ($data['passThreshold'] ?? 0.75);
        $passLabel     = $data['passLabel'] ?? 'Completed';
        $failLabel     = $data['failLabel'] ?? 'Not Completed';

        $results = [];

        foreach ($students as $student) {
            $studentId    = $student['id'];
            $scores       = $student['scores'] ?? [];
            $categoryRows = [];

            foreach ($categories as $category) {
                $catId       = $category['id'];
                $catCode     = $category['code'];
                $weight      = (float) $category['weight'];
                $assessments = $category['assessments'] ?? [];

                $rawScores = [];
                $maxScores = [];
                foreach ($assessments as $assessment) {
                    $aid         = $assessment['id'];
                    $rawScores[] = $scores[$aid] ?? null;
                    $maxScores[] = (float) $assessment['maxScore'];
                }

                $catResult = $this->computeCategoryScore($rawScores, $maxScores, $weight);
                $categoryRows[] = array_merge($catResult, [
                    'categoryId'   => $catId,
                    'categoryCode' => $catCode,
                    'weight'       => $weight,
                ]);
            }

            $compliance = $this->computeComplianceCategory($categoryRows, $passThreshold, $passLabel, $failLabel);

            $results[] = [
                'studentId'             => $studentId,
                'categories'            => $categoryRows,
                'compliancePercentage'  => $compliance['compliancePercentage'],
                'remark'                => $compliance['remark'],
                'passed'                => $compliance['passed'],
            ];
        }

        return ['students' => $results];
    }

    /**
     * Combine four quarters' compliance percentages into a school-year-end
     * remark, per the grading option's configured rule:
     *   - 'all_quarters_pass': every quarter individually met the pass
     *     threshold — one failing quarter fails the whole school year.
     *   - 'average_threshold': the average of the 4 quarters' percentages is
     *     compared against the same pass threshold.
     * A null entry (quarter never graded/no data) counts as not passed —
     * an incomplete school year cannot silently read as "Completed".
     *
     * @param  array<float|null> $quarterPercentages  four 0–100 values, one per quarter (null = ungraded)
     * @param  string            $rule                'all_quarters_pass' | 'average_threshold'
     * @param  float             $passThreshold        0–1
     */
    public function computeComplianceSchoolYearRemark(
        array $quarterPercentages,
        string $rule,
        float $passThreshold,
        string $passLabel,
        string $failLabel,
    ): array {
        $thresholdPct = $passThreshold * 100;

        if ($rule === 'average_threshold') {
            $present = array_filter($quarterPercentages, fn ($v) => $v !== null);
            $average = count($present) > 0 ? array_sum($present) / count($present) : 0.0;
            $passed  = count($present) === count($quarterPercentages) && $average >= $thresholdPct;

            return [
                'averagePercentage' => round($average, 2),
                'remark'            => $passed ? $passLabel : $failLabel,
                'passed'            => $passed,
            ];
        }

        // 'all_quarters_pass' (default): every quarter must individually meet the threshold.
        $passed = count($quarterPercentages) > 0
            && array_reduce(
                $quarterPercentages,
                fn ($carry, $pct) => $carry && $pct !== null && $pct >= $thresholdPct,
                true,
            );

        $present = array_filter($quarterPercentages, fn ($v) => $v !== null);
        $average = count($present) > 0 ? array_sum($present) / count($present) : 0.0;

        return [
            'averagePercentage' => round($average, 2),
            'remark'            => $passed ? $passLabel : $failLabel,
            'passed'            => $passed,
        ];
    }
}
