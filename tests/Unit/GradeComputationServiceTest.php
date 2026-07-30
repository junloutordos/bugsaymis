<?php

namespace Tests\Unit;

use App\Services\ClassRecord\GradeComputationService;
use Tests\TestCase;

class GradeComputationServiceTest extends TestCase
{
    private GradeComputationService $service;

    /** Full 62-row stanine lookup (percentage → GE → adjectival) */
    private array $stanine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GradeComputationService();
        $this->stanine = $this->buildStanine();
    }

    // ── Method 1: computeCategoryScore ───────────────────────────────────────

    /**
     * PEHM regression: GradeComputationService takes categories as a flat
     * list of leaves with an id/code/weight — it has no concept of
     * "subject_id" at all (that's purely an access-control concern living on
     * GradingCategory/ClassRecord). A "Summative Assessments" parent split
     * into PE/Health/Music leaf sub-categories must sum/weight identically to
     * any other multi-leaf-under-one-parent grading option — each leaf's
     * assessments are scored independently by its own teacher, then the
     * category weights combine exactly like Written Works vs Performance
     * Tasks would on a normal single-teacher subject.
     */
    /** @test */
    public function it_computes_full_class_record_for_a_pehm_style_multi_subject_leaf_split(): void
    {
        // PE 40%, Health 30%, Music 30% — one leaf per subject, summing to 100%.
        $categories = [
            [
                'id' => 1, 'code' => 'PE', 'weight' => 0.40,
                'assessments' => [['id' => 101, 'maxScore' => 20], ['id' => 102, 'maxScore' => 20]],
            ],
            [
                'id' => 2, 'code' => 'HEA', 'weight' => 0.30,
                'assessments' => [['id' => 201, 'maxScore' => 30]],
            ],
            [
                'id' => 3, 'code' => 'MUS', 'weight' => 0.30,
                'assessments' => [['id' => 301, 'maxScore' => 25]],
            ],
        ];

        // Student aced PE (40/40), got 24/30 in Health, 20/25 in Music.
        $students = [[
            'id' => 1,
            'scores' => [101 => 20, 102 => 20, 201 => 24, 301 => 20],
        ]];

        $result = $this->service->computeFullClassRecord([
            'quarter' => 1,
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
            'stanineLookup' => $this->stanine,
            'previousQuarterGrades' => [],
        ]);

        $row = $result['students'][0];

        // PE: 40/40 = 100% * 0.40 = 0.40
        // Health: 24/30 = 80% * 0.30 = 0.24
        // Music: 20/25 = 80% * 0.30 = 0.24
        // Total weighted = 0.40 + 0.24 + 0.24 = 0.88 -> 88%
        $this->assertEqualsWithDelta(0.88, $row['totalWeightedPercentage'], 0.0001);
        $this->assertEquals(88.0, $row['percentageScore']);

        $peCategory = collect($row['categories'])->firstWhere('categoryCode', 'PE');
        $this->assertEquals(40.0, $peCategory['total']);
        $this->assertEquals(40.0, $peCategory['maxTotal']);
        $this->assertEqualsWithDelta(1.0, $peCategory['percentage'], 0.0001);

        $healthCategory = collect($row['categories'])->firstWhere('categoryCode', 'HEA');
        $this->assertEqualsWithDelta(0.8, $healthCategory['percentage'], 0.0001);

        $musicCategory = collect($row['categories'])->firstWhere('categoryCode', 'MUS');
        $this->assertEqualsWithDelta(0.8, $musicCategory['percentage'], 0.0001);
    }

    /**
     * A student with a missing score under ONE subject's leaf (e.g. absent
     * for the Music assessment) must not zero out the other two subjects —
     * each leaf's percentage is computed independently, exactly as it would
     * be for any other missing-assessment scenario on a normal record.
     */
    /** @test */
    public function it_treats_a_missing_score_on_one_pehm_subject_leaf_independently_of_the_others(): void
    {
        $categories = [
            ['id' => 1, 'code' => 'PE', 'weight' => 0.40, 'assessments' => [['id' => 101, 'maxScore' => 20]]],
            ['id' => 2, 'code' => 'HEA', 'weight' => 0.30, 'assessments' => [['id' => 201, 'maxScore' => 30]]],
            ['id' => 3, 'code' => 'MUS', 'weight' => 0.30, 'assessments' => [['id' => 301, 'maxScore' => 25]]],
        ];

        // Music score never entered (null == absent/not yet graded).
        $students = [[
            'id' => 1,
            'scores' => [101 => 20, 201 => 30, 301 => null],
        ]];

        $result = $this->service->computeFullClassRecord([
            'quarter' => 1,
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
            'stanineLookup' => $this->stanine,
            'previousQuarterGrades' => [],
        ]);

        $row = $result['students'][0];
        // PE 100% * .40 = .40, Health 100% * .30 = .30, Music 0% * .30 = 0
        $this->assertEqualsWithDelta(0.70, $row['totalWeightedPercentage'], 0.0001);
    }

    /** @test */
    public function it_computes_category_score_with_all_scores_present(): void
    {
        $result = $this->service->computeCategoryScore(
            scores:    [17, 20, 10],
            maxScores: [20, 20, 30],
            weight:    0.70,
        );

        $this->assertEquals(47,          $result['total']);
        $this->assertEquals(70,          $result['maxTotal']);
        $this->assertEqualsWithDelta(47 / 70, $result['percentage'], 0.0001);
        $this->assertEqualsWithDelta((47 / 70) * 0.70, $result['weightedPercentage'], 0.0001);
    }

    /** @test */
    public function it_treats_null_scores_as_zero(): void
    {
        $result = $this->service->computeCategoryScore(
            scores:    [17, null, 10],
            maxScores: [20, 20, 30],
            weight:    0.40,
        );

        $this->assertEquals(27,          $result['total']);
        $this->assertEquals(70,          $result['maxTotal']);
        $this->assertEqualsWithDelta(27 / 70, $result['percentage'], 0.0001);
        $this->assertEqualsWithDelta((27 / 70) * 0.40, $result['weightedPercentage'], 0.0001);
    }

    // ── Method 4: lookupGradeEquivalent ──────────────────────────────────────

    /** @test */
    public function it_looks_up_grade_equivalent_at_boundaries(): void
    {
        $cases = [
            [100, 1.000, 'Excellent'],
            [95,  1.140, 'Very Good'],
            [83,  1.640, 'Good'],
            [75,  1.980, 'Good'],
            [60,  2.600, 'Satisfactory'],
            [50,  3.379, 'Fair'],
            [49,  3.510, 'Failed on Condition'],
            [39,  4.510, 'Failed'],
        ];

        foreach ($cases as [$pct, $expectedGE, $expectedAdj]) {
            $result = $this->service->lookupGradeEquivalent($pct, $this->stanine);
            $this->assertEqualsWithDelta($expectedGE, $result['gradeEquivalent'], 0.001,
                "GE mismatch at {$pct}%");
            $this->assertEquals($expectedAdj, $result['adjectivalEquivalent'],
                "Adjectival mismatch at {$pct}%");
        }
    }

    /** @test */
    public function it_clamps_percentage_below_39_to_failed(): void
    {
        $result = $this->service->lookupGradeEquivalent(20, $this->stanine);
        $this->assertEqualsWithDelta(4.510, $result['gradeEquivalent'], 0.001);
        $this->assertEquals('Failed', $result['adjectivalEquivalent']);
    }

    // ── Method 3: computeRunningGrade ────────────────────────────────────────

    /** @test */
    public function it_returns_current_ge_for_quarter_1(): void
    {
        $result = $this->service->computeRunningGrade(
            currentQuarterGE:      1.500,
            previousRunningGrade:  null,
            quarter:               1,
            stanineLookup:         $this->stanine,
        );

        $this->assertEqualsWithDelta(1.500, $result['finalGrade'], 0.001);
    }

    /** @test */
    public function it_applies_rolling_formula_for_quarter_2(): void
    {
        // Q2: (currentGE × 2/3) + (prevGrade × 1/3)
        // = (1.770 × 2/3) + (1.500 × 1/3)
        // = 1.180 + 0.500 = 1.680 → floor to 3dp = 1.680
        $result = $this->service->computeRunningGrade(
            currentQuarterGE:     1.770,
            previousRunningGrade: 1.500,
            quarter:              2,
            stanineLookup:        $this->stanine,
        );

        $this->assertEqualsWithDelta(1.680, $result['finalGrade'], 0.001);
    }

    /** @test */
    public function it_floors_not_rounds_for_truncation(): void
    {
        // (2.060 × 2/3) + (1.980 × 1/3)
        // = 1.37333... + 0.66000 = 2.03333...
        // floor to 3dp → 2.033 (NOT 2.034 which rounding would give)
        $result = $this->service->computeRunningGrade(
            currentQuarterGE:     2.060,
            previousRunningGrade: 1.980,
            quarter:              3,
            stanineLookup:        $this->stanine,
        );

        $this->assertEqualsWithDelta(2.033, $result['finalGrade'], 0.0001);
        // Explicitly confirm it did NOT round up
        $this->assertLessThan(2.034, $result['finalGrade']);
    }

    // ── Compliance-mode methods ───────────────────────────────────────────────

    /** @test */
    public function it_computes_compliance_category_result_above_threshold(): void
    {
        // 3 of 4 activities complied = 75% weighted, threshold 75% → pass.
        $categoryResults = [
            ['percentage' => 0.75, 'weight' => 1.0],
        ];

        $result = $this->service->computeComplianceCategory($categoryResults, 0.75, 'Completed', 'Not Completed');

        $this->assertEquals(75.0, $result['compliancePercentage']);
        $this->assertTrue($result['passed']);
        $this->assertEquals('Completed', $result['remark']);
    }

    /** @test */
    public function it_computes_compliance_category_result_below_threshold(): void
    {
        $categoryResults = [
            ['percentage' => 0.50, 'weight' => 1.0],
        ];

        $result = $this->service->computeComplianceCategory($categoryResults, 0.75, 'Completed', 'Not Completed');

        $this->assertEquals(50.0, $result['compliancePercentage']);
        $this->assertFalse($result['passed']);
        $this->assertEquals('Not Completed', $result['remark']);
    }

    /** @test */
    public function it_combines_multiple_weighted_categories_for_compliance(): void
    {
        // Category A: 100% complied, weight 0.6 -> 0.60
        // Category B: 50% complied, weight 0.4 -> 0.20
        // Total = 0.80 = 80%, threshold 75% -> pass
        $categoryResults = [
            ['percentage' => 1.0, 'weight' => 0.6],
            ['percentage' => 0.5, 'weight' => 0.4],
        ];

        $result = $this->service->computeComplianceCategory($categoryResults, 0.75, 'Completed', 'Not Completed');

        $this->assertEquals(80.0, $result['compliancePercentage']);
        $this->assertTrue($result['passed']);
    }

    /** @test */
    public function it_respects_custom_pass_and_fail_labels(): void
    {
        $categoryResults = [['percentage' => 1.0, 'weight' => 1.0]];

        $passed = $this->service->computeComplianceCategory($categoryResults, 0.75, 'Passed', 'Did Not Pass');
        $this->assertEquals('Passed', $passed['remark']);

        $failed = $this->service->computeComplianceCategory([['percentage' => 0.1, 'weight' => 1.0]], 0.75, 'Passed', 'Did Not Pass');
        $this->assertEquals('Did Not Pass', $failed['remark']);
    }

    /** @test */
    public function it_computes_full_compliance_class_record_for_multiple_students(): void
    {
        $categories = [
            ['id' => 1, 'code' => 'ACT', 'weight' => 1.0, 'assessments' => [
                ['id' => 101, 'maxScore' => 1],
                ['id' => 102, 'maxScore' => 1],
                ['id' => 103, 'maxScore' => 1],
                ['id' => 104, 'maxScore' => 1],
            ]],
        ];

        $students = [
            ['id' => 1, 'scores' => [101 => 1, 102 => 1, 103 => 1, 104 => 1]],  // 100% -> pass
            ['id' => 2, 'scores' => [101 => 1, 102 => 0, 103 => 0, 104 => 0]],  // 25% -> fail
        ];

        $result = $this->service->computeFullComplianceClassRecord([
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
            'passThreshold' => 0.75,
            'passLabel' => 'Completed',
            'failLabel' => 'Not Completed',
        ]);

        $byId = collect($result['students'])->keyBy('studentId');
        $this->assertEquals(100.0, $byId[1]['compliancePercentage']);
        $this->assertTrue($byId[1]['passed']);
        $this->assertEquals('Completed', $byId[1]['remark']);

        $this->assertEquals(25.0, $byId[2]['compliancePercentage']);
        $this->assertFalse($byId[2]['passed']);
        $this->assertEquals('Not Completed', $byId[2]['remark']);
    }

    /** @test */
    public function it_treats_missing_scores_as_not_complied_in_full_compliance_class_record(): void
    {
        $categories = [
            ['id' => 1, 'code' => 'ACT', 'weight' => 1.0, 'assessments' => [
                ['id' => 101, 'maxScore' => 1],
                ['id' => 102, 'maxScore' => 1],
            ]],
        ];

        // Student never scored on assessment 102 at all (absent from scores array).
        $students = [['id' => 1, 'scores' => [101 => 1]]];

        $result = $this->service->computeFullComplianceClassRecord([
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
            'passThreshold' => 0.75,
            'passLabel' => 'Completed',
            'failLabel' => 'Not Completed',
        ]);

        $this->assertEquals(50.0, $result['students'][0]['compliancePercentage']);
        $this->assertFalse($result['students'][0]['passed']);
    }

    /** @test */
    public function it_defaults_pass_threshold_and_labels_when_omitted_from_full_compliance_class_record(): void
    {
        $categories = [['id' => 1, 'code' => 'ACT', 'weight' => 1.0, 'assessments' => [['id' => 101, 'maxScore' => 1]]]];
        $students = [['id' => 1, 'scores' => [101 => 1]]];

        $result = $this->service->computeFullComplianceClassRecord([
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
        ]);

        // Defaults: passThreshold 0.75, passLabel 'Completed', failLabel 'Not Completed'.
        $this->assertEquals('Completed', $result['students'][0]['remark']);
        $this->assertTrue($result['students'][0]['passed']);
    }

    /** @test */
    public function it_passes_school_year_remark_when_all_quarters_meet_threshold(): void
    {
        $result = $this->service->computeComplianceSchoolYearRemark(
            [80.0, 90.0, 75.0, 100.0], 'all_quarters_pass', 0.75, 'Completed', 'Not Completed',
        );

        $this->assertTrue($result['passed']);
        $this->assertEquals('Completed', $result['remark']);
        $this->assertEqualsWithDelta(86.25, $result['averagePercentage'], 0.01);
    }

    /** @test */
    public function it_fails_school_year_remark_under_all_quarters_pass_rule_when_one_quarter_misses(): void
    {
        // Q3 is below the 75% threshold — must fail the whole SY even though
        // the average across all 4 would otherwise be comfortably above it.
        $result = $this->service->computeComplianceSchoolYearRemark(
            [90.0, 90.0, 50.0, 90.0], 'all_quarters_pass', 0.75, 'Completed', 'Not Completed',
        );

        $this->assertFalse($result['passed']);
        $this->assertEquals('Not Completed', $result['remark']);
    }

    /** @test */
    public function it_fails_school_year_remark_under_all_quarters_pass_rule_when_a_quarter_is_ungraded(): void
    {
        // Q4 was never graded (null) — an incomplete school year must never
        // silently read as Completed just because the other 3 quarters passed.
        $result = $this->service->computeComplianceSchoolYearRemark(
            [90.0, 90.0, 90.0, null], 'all_quarters_pass', 0.75, 'Completed', 'Not Completed',
        );

        $this->assertFalse($result['passed']);
        $this->assertEquals('Not Completed', $result['remark']);
    }

    /** @test */
    public function it_passes_school_year_remark_under_average_threshold_rule_when_average_meets_it(): void
    {
        // Q3 individually dips below 75%, but the AVERAGE of all 4 is still
        // above threshold — average_threshold rule should pass here even
        // though all_quarters_pass would not.
        $result = $this->service->computeComplianceSchoolYearRemark(
            [90.0, 90.0, 60.0, 90.0], 'average_threshold', 0.75, 'Completed', 'Not Completed',
        );

        $this->assertEqualsWithDelta(82.5, $result['averagePercentage'], 0.01);
        $this->assertTrue($result['passed']);
        $this->assertEquals('Completed', $result['remark']);
    }

    /** @test */
    public function it_fails_school_year_remark_under_average_threshold_rule_when_average_misses_it(): void
    {
        $result = $this->service->computeComplianceSchoolYearRemark(
            [60.0, 60.0, 60.0, 60.0], 'average_threshold', 0.75, 'Completed', 'Not Completed',
        );

        $this->assertEqualsWithDelta(60.0, $result['averagePercentage'], 0.01);
        $this->assertFalse($result['passed']);
    }

    /** @test */
    public function it_fails_school_year_remark_under_average_threshold_rule_when_a_quarter_is_missing_even_if_average_would_pass(): void
    {
        // Only 3 of 4 quarters have data, and their average is well above
        // threshold — but a school year isn't complete without all 4
        // quarters, so this must still fail regardless of the rule chosen.
        $result = $this->service->computeComplianceSchoolYearRemark(
            [100.0, 100.0, 100.0, null], 'average_threshold', 0.75, 'Completed', 'Not Completed',
        );

        $this->assertFalse($result['passed']);
        $this->assertEquals('Not Completed', $result['remark']);
    }

    /** @test */
    public function it_uses_custom_labels_for_school_year_remark(): void
    {
        $passed = $this->service->computeComplianceSchoolYearRemark(
            [100.0, 100.0, 100.0, 100.0], 'all_quarters_pass', 0.75, 'Passed the Year', 'Did Not Pass the Year',
        );
        $this->assertEquals('Passed the Year', $passed['remark']);

        $failed = $this->service->computeComplianceSchoolYearRemark(
            [10.0, 10.0, 10.0, 10.0], 'all_quarters_pass', 0.75, 'Passed the Year', 'Did Not Pass the Year',
        );
        $this->assertEquals('Did Not Pass the Year', $failed['remark']);
    }

    // ── Stanine lookup data ───────────────────────────────────────────────────

    private function buildStanine(): array
    {
        $data = [
            [100, 1.000, 'Excellent'],        [99, 1.027, 'Excellent'],
            [98, 1.053, 'Excellent'],         [97, 1.079, 'Excellent'],
            [96, 1.105, 'Excellent'],         [95, 1.140, 'Very Good'],
            [94, 1.190, 'Very Good'],         [93, 1.230, 'Very Good'],
            [92, 1.270, 'Very Good'],         [91, 1.310, 'Very Good'],
            [90, 1.350, 'Very Good'],         [89, 1.390, 'Very Good'],
            [88, 1.440, 'Very Good'],         [87, 1.480, 'Very Good'],
            [86, 1.520, 'Very Good'],         [85, 1.560, 'Very Good'],
            [84, 1.600, 'Very Good'],         [83, 1.640, 'Good'],
            [82, 1.690, 'Good'],              [81, 1.730, 'Good'],
            [80, 1.770, 'Good'],              [79, 1.810, 'Good'],
            [78, 1.850, 'Good'],              [77, 1.890, 'Good'],
            [76, 1.940, 'Good'],              [75, 1.980, 'Good'],
            [74, 2.020, 'Good'],              [73, 2.060, 'Good'],
            [72, 2.100, 'Good'],              [71, 2.140, 'Satisfactory'],
            [70, 2.190, 'Satisfactory'],      [69, 2.230, 'Satisfactory'],
            [68, 2.270, 'Satisfactory'],      [67, 2.310, 'Satisfactory'],
            [66, 2.350, 'Satisfactory'],      [65, 2.390, 'Satisfactory'],
            [64, 2.440, 'Satisfactory'],      [63, 2.480, 'Satisfactory'],
            [62, 2.520, 'Satisfactory'],      [61, 2.560, 'Satisfactory'],
            [60, 2.600, 'Satisfactory'],      [59, 2.640, 'Fair'],
            [58, 2.689, 'Fair'],              [57, 2.737, 'Fair'],
            [56, 2.785, 'Fair'],              [55, 2.833, 'Fair'],
            [54, 2.890, 'Fair'],              [53, 3.013, 'Fair'],
            [52, 3.135, 'Fair'],              [51, 3.257, 'Fair'],
            [50, 3.379, 'Fair'],              [49, 3.510, 'Failed on Condition'],
            [48, 3.610, 'Failed on Condition'],[47, 3.709, 'Failed on Condition'],
            [46, 3.808, 'Failed on Condition'],[45, 3.907, 'Failed on Condition'],
            [44, 4.006, 'Failed on Condition'],[43, 4.105, 'Failed on Condition'],
            [42, 4.204, 'Failed on Condition'],[41, 4.303, 'Failed on Condition'],
            [40, 4.402, 'Failed on Condition'],[39, 4.510, 'Failed'],
        ];

        return array_map(fn ($r) => [
            'percentage'            => $r[0],
            'grade_equivalent'      => $r[1],
            'adjectival_equivalent' => $r[2],
        ], $data);
    }
}
