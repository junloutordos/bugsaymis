<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\LoadComputationService;
use Tests\TestCase;

/**
 * Unit tests for LoadComputationService.
 * Verifies all rules from BOT Resolution No. 2025-05-80.
 */
class LoadComputationTest extends TestCase
{
    private LoadComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LoadComputationService();
    }

    // ── Load Status ──────────────────────────────────────────────────────────

    /** @test */
    public function it_classifies_underload_when_total_is_below_threshold(): void
    {
        $this->assertSame('underload', $this->service->getLoadStatus(17.0));
        $this->assertSame('underload', $this->service->getLoadStatus(0.0));
        $this->assertSame('underload', $this->service->getLoadStatus(17.99));
    }

    /** @test */
    public function it_classifies_full_load_when_total_equals_threshold(): void
    {
        $this->assertSame('full_load', $this->service->getLoadStatus(18.0));
    }

    /** @test */
    public function it_classifies_overload_when_total_exceeds_threshold(): void
    {
        $this->assertSame('overload', $this->service->getLoadStatus(18.01));
        $this->assertSame('overload', $this->service->getLoadStatus(24.0));
    }

    /** @test */
    public function it_respects_custom_threshold(): void
    {
        $this->assertSame('underload', $this->service->getLoadStatus(17.0, 20.0));
        $this->assertSame('full_load', $this->service->getLoadStatus(20.0, 20.0));
        $this->assertSame('overload',  $this->service->getLoadStatus(21.0, 20.0));
    }

    // ── Overload / Underload Units ───────────────────────────────────────────

    /** @test */
    public function it_computes_overload_units_correctly(): void
    {
        $this->assertSame(0.0,  $this->service->getOverloadUnits(18.0));
        $this->assertSame(0.0,  $this->service->getOverloadUnits(17.0));
        $this->assertSame(2.0,  $this->service->getOverloadUnits(20.0));
        $this->assertSame(0.5,  $this->service->getOverloadUnits(18.5));
    }

    /** @test */
    public function it_computes_underload_units_correctly(): void
    {
        $this->assertSame(0.0,  $this->service->getUnderloadUnits(18.0));
        $this->assertSame(1.0,  $this->service->getUnderloadUnits(17.0));
        $this->assertSame(18.0, $this->service->getUnderloadUnits(0.0));
        $this->assertSame(0.0,  $this->service->getUnderloadUnits(20.0)); // overload → 0 underload
    }

    // ── PHTR Computation ─────────────────────────────────────────────────────

    /** @test */
    public function it_computes_phtr_using_bot_resolution_formula(): void
    {
        // PHTR = (Annual Rate / 1600) × 1.25
        $annualRate = 400_000;
        $expected   = round((400_000 / 1600) * 1.25, 4); // 312.5000
        $this->assertSame($expected, $this->service->computePHTR($annualRate));
    }

    /** @test */
    public function it_returns_zero_phtr_for_zero_or_negative_annual_rate(): void
    {
        $this->assertSame(0.0, $this->service->computePHTR(0));
        $this->assertSame(0.0, $this->service->computePHTR(-100));
    }

    /** @test */
    public function it_computes_phtr_for_various_salary_grades(): void
    {
        // SG-19 (approx. PHP 77,000/mo × 12 = 924,000/yr)
        $ar = 924_000;
        $expected = round(($ar / 1600) * 1.25, 4);
        $this->assertSame($expected, $this->service->computePHTR($ar));
    }

    // ── Overload Pay ─────────────────────────────────────────────────────────

    /** @test */
    public function it_computes_overload_pay_correctly(): void
    {
        $phtr           = 312.5;
        $overloadHours  = 3.0;  // 3 hrs/week overload
        $termWeeks      = 18;

        $expected = round(312.5 * 3.0 * 18, 2); // 16,875.00
        $this->assertSame($expected, $this->service->computeOverloadPay($phtr, $overloadHours, $termWeeks));
    }

    /** @test */
    public function it_returns_zero_overload_pay_for_zero_inputs(): void
    {
        $this->assertSame(0.0, $this->service->computeOverloadPay(0, 3, 18));
        $this->assertSame(0.0, $this->service->computeOverloadPay(312.5, 0, 18));
        $this->assertSame(0.0, $this->service->computeOverloadPay(312.5, 3, 0));
    }

    /** @test */
    public function it_returns_full_overload_breakdown(): void
    {
        $result = $this->service->computeOverloadBreakdown(
            annualRate:          400_000,
            overloadUnits:       3.0,
            overloadHoursPerWeek: 3.0,
            termWeeks:           18
        );

        $this->assertArrayHasKey('phtr',               $result);
        $this->assertArrayHasKey('total_overload_pay', $result);
        $this->assertArrayHasKey('term_weeks',         $result);
        $this->assertSame(312.5,     $result['phtr']);
        $this->assertSame(3.0,       $result['overload_units']);
        $this->assertSame(16875.0,   $result['total_overload_pay']);
    }

    // ── Admin Load Limit ─────────────────────────────────────────────────────

    /** @test */
    public function it_passes_admin_load_within_15_unit_limit(): void
    {
        $result = $this->service->validateAdminLoad(15.0);
        $this->assertTrue($result['valid']);

        $result = $this->service->validateAdminLoad(10.0);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function it_fails_admin_load_exceeding_15_units(): void
    {
        $result = $this->service->validateAdminLoad(15.01);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('15', $result['message']);
    }

    /** @test */
    public function it_passes_zero_admin_load(): void
    {
        $result = $this->service->validateAdminLoad(0.0);
        $this->assertTrue($result['valid']);
    }

    // ── Research Advisory Limit ──────────────────────────────────────────────

    /** @test */
    public function it_passes_research_load_within_5_unit_limit(): void
    {
        $result = $this->service->validateResearchLoad(5.0);
        $this->assertTrue($result['valid']);

        $result = $this->service->validateResearchLoad(3.5);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function it_fails_research_load_exceeding_5_units(): void
    {
        $result = $this->service->validateResearchLoad(5.01);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('5', $result['message']);
    }

    // ── Daily Teaching Hours ─────────────────────────────────────────────────

    /** @test */
    public function it_returns_ok_level_for_normal_daily_hours(): void
    {
        $result = $this->service->validateDailyTeachingHours(6.0);
        $this->assertSame('ok', $result['level']);

        $result = $this->service->validateDailyTeachingHours(3.0);
        $this->assertSame('ok', $result['level']);
    }

    /** @test */
    public function it_returns_warning_level_for_hours_between_6_and_8(): void
    {
        $result = $this->service->validateDailyTeachingHours(7.0);
        $this->assertSame('warning', $result['level']);

        $result = $this->service->validateDailyTeachingHours(8.0);
        $this->assertSame('warning', $result['level']);
    }

    /** @test */
    public function it_returns_exceeded_level_for_hours_over_8(): void
    {
        $result = $this->service->validateDailyTeachingHours(8.01);
        $this->assertSame('exceeded', $result['level']);

        $result = $this->service->validateDailyTeachingHours(10.0);
        $this->assertSame('exceeded', $result['level']);
    }

    // ── Edge Cases ───────────────────────────────────────────────────────────

    /** @test */
    public function full_load_threshold_constant_is_18(): void
    {
        $this->assertSame(18.0, LoadComputationService::FULL_LOAD_THRESHOLD);
    }

    /** @test */
    public function max_admin_units_constant_is_15(): void
    {
        $this->assertSame(15.0, LoadComputationService::MAX_ADMIN_UNITS);
    }

    /** @test */
    public function max_research_units_constant_is_5(): void
    {
        $this->assertSame(5.0, LoadComputationService::MAX_RESEARCH_UNITS);
    }

    /** @test */
    public function phtr_divisor_and_multiplier_match_bot_resolution(): void
    {
        $this->assertSame(1600,  LoadComputationService::PHTR_DIVISOR);
        $this->assertSame(1.25,  LoadComputationService::PHTR_MULTIPLIER);
    }
}
