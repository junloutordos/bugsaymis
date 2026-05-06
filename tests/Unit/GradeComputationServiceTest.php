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
