<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicFunctionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rating_fiscal_year_returns_current_period_year_minus_one(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $this->assertSame(2025, (new StrategicFunctionService())->ratingFiscalYear());
    }

    public function test_rating_fiscal_year_returns_null_when_no_current_period(): void
    {
        $this->assertNull((new StrategicFunctionService())->ratingFiscalYear());
    }

    public function test_returns_the_prior_fiscal_years_indicators_grouped_by_program(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $programB = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program']);
        $programA = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);

        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programB->id, 'description' => 'Indicator B1']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programA->id, 'description' => 'Indicator A1']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $programA->id, 'description' => 'Current year, excluded']);

        $result = (new StrategicFunctionService())->currentIndicators();

        $this->assertCount(2, $result);
        $this->assertSame('Indicator A1', $result->first()->description);
    }

    public function test_returns_empty_when_the_prior_fiscal_year_has_no_indicators(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $program = AgencyOutcome::create(['outcome' => 'A']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'Current year only, should not surface']);

        $result = (new StrategicFunctionService())->currentIndicators();

        $this->assertCount(0, $result);
    }

    public function test_current_indicators_resolve_dost_strategy_and_sub_strategy_text(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $pillar = \App\Models\DostPillar::create(['name' => 'Pillar 1']);
        $strategy = \App\Models\DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1: Achieve quality science education']);
        \App\Models\DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Institutionalized FORWARD program']);

        $program = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        $program->dostStrategies()->attach($strategy->id);

        $performanceIndicator = \App\Models\PerformanceIndicator::create(['agency_outcome_id' => $program->id, 'description' => 'PI 1']);

        OpcrIndicator::create([
            'fiscal_year' => 2025,
            'agency_outcome_id' => $program->id,
            'performance_indicator_id' => $performanceIndicator->id,
            'description' => 'Indicator 1',
        ]);

        $result = (new StrategicFunctionService())->currentIndicators();

        $source = $result->first()->performanceIndicator?->agencyOutcome ?? $result->first()->agencyOutcome;
        $this->assertSame('Strategy 1: Achieve quality science education', $source->dost_strategy_names_joined);
        $this->assertSame('Institutionalized FORWARD program', $source->dost_sub_strategy_descriptions_joined);
    }
}
