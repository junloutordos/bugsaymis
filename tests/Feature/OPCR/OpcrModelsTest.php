<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrPeriod;
use App\Models\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_belongs_to_period_and_walks_up_the_dost_hierarchy(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Institutionalize FORWARD Framework']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);

        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'dost_sub_strategy_id' => $subStrategy->id,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Percentage of freshmen with GWA 2.5 or better',
            'target' => '0.96',
        ]);
        $indicator->divisions()->sync([$division->id]);

        $this->assertTrue($period->indicators->contains($indicator));
        $this->assertEquals($subStrategy->id, $indicator->subStrategy->id);
        $this->assertEquals($strategy->id, $indicator->subStrategy->strategy->id);
        $this->assertEquals($pillar->id, $indicator->subStrategy->strategy->pillar->id);
        $this->assertEquals('A. STEM Secondary Education', $indicator->agencyOutcome->outcome);
        $this->assertTrue($indicator->divisions->contains($division));
    }

    public function test_indicator_optionally_cross_references_an_existing_performance_indicator(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Existing IPCR indicator',
            'target' => '10',
        ]);

        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'performance_indicator_id' => $pi->id,
            'description' => 'OPCR indicator, cross-referenced',
        ]);

        $this->assertEquals($pi->id, $indicator->performanceIndicator->id);
    }

    public function test_indicator_without_dost_tagging_or_cross_reference_is_still_valid(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);

        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'description' => 'Untagged indicator',
        ]);

        $this->assertNull($indicator->subStrategy);
        $this->assertNull($indicator->agencyOutcome);
        $this->assertNull($indicator->performanceIndicator);
    }

    public function test_actuals_track_one_row_per_quarter(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);

        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.5']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 2, 'value' => '0.7']);

        $this->assertCount(2, $indicator->fresh()->actuals);
        $this->assertEquals('0.5', $indicator->actuals()->where('quarter', 1)->first()->value);
    }

    public function test_scope_current_returns_only_the_current_period(): void
    {
        OpcrPeriod::create(['fiscal_year' => 2025, 'period_label' => 'FY2025', 'is_current' => false]);
        $current = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => true]);

        $result = OpcrPeriod::current()->get();

        $this->assertCount(1, $result);
        $this->assertEquals($current->id, $result->first()->id);
    }
}
