<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrSetting;
use App\Models\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_walks_up_the_dost_hierarchy(): void
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Institutionalize FORWARD Framework']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);

        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'dost_sub_strategy_id' => $subStrategy->id,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Percentage of freshmen with GWA 2.5 or better',
            'target' => '0.96',
        ]);
        $indicator->divisions()->sync([$division->id]);

        $this->assertEquals($subStrategy->id, $indicator->subStrategy->id);
        $this->assertEquals($strategy->id, $indicator->subStrategy->strategy->id);
        $this->assertEquals($pillar->id, $indicator->subStrategy->strategy->pillar->id);
        $this->assertEquals('A. STEM Secondary Education', $indicator->agencyOutcome->outcome);
        $this->assertTrue($indicator->divisions->contains($division));
    }

    public function test_indicator_optionally_cross_references_an_existing_performance_indicator(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Existing IPCR indicator',
            'target' => '10',
        ]);

        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'performance_indicator_id' => $pi->id,
            'description' => 'OPCR indicator, cross-referenced',
        ]);

        $this->assertEquals($pi->id, $indicator->performanceIndicator->id);
    }

    public function test_indicator_without_dost_tagging_or_cross_reference_is_still_valid(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Untagged indicator',
        ]);

        $this->assertNull($indicator->subStrategy);
        $this->assertNull($indicator->performanceIndicator);
        $this->assertEquals($outcome->id, $indicator->agencyOutcome->id);
    }

    public function test_actuals_track_one_row_per_quarter(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);

        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.5']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 2, 'value' => '0.7']);

        $this->assertCount(2, $indicator->fresh()->actuals);
        $this->assertEquals('0.5', $indicator->actuals()->where('quarter', 1)->first()->value);
    }

    public function test_scope_for_fiscal_year_filters_correctly(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $outcome->id, 'description' => 'FY2025 indicator']);
        $fy2026 = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'FY2026 indicator']);

        $result = OpcrIndicator::forFiscalYear(2026)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($fy2026->id, $result->first()->id);
    }

    public function test_accomplishment_summary_joins_present_quarters_and_skips_empty_ones(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '80%']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 3, 'value' => '90%']);

        $fresh = OpcrIndicator::with('actuals')->find($indicator->id);

        $this->assertSame('Q1: 80%; Q3: 90%', $fresh->accomplishment_summary);
    }

    public function test_accomplishment_summary_is_null_when_no_quarters_have_a_value(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);

        $fresh = OpcrIndicator::with('actuals')->find($indicator->id);

        $this->assertNull($fresh->accomplishment_summary);
    }

    public function test_accomplishment_summary_includes_all_four_quarters_when_all_are_present(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);
        foreach ([1 => '80%', 2 => '85%', 3 => '90%', 4 => '95%'] as $quarter => $value) {
            OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => $quarter, 'value' => $value]);
        }

        $fresh = OpcrIndicator::with('actuals')->find($indicator->id);

        $this->assertSame('Q1: 80%; Q2: 85%; Q3: 90%; Q4: 95%', $fresh->accomplishment_summary);
    }

    public function test_displayed_accomplishment_falls_back_to_the_auto_summary_when_no_manual_override_is_set(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '80%']);

        $fresh = OpcrIndicator::with('actuals')->find($indicator->id);

        $this->assertSame('Q1: 80%', $fresh->displayed_accomplishment);
    }

    public function test_displayed_accomplishment_prefers_the_manual_override_over_the_auto_summary(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Indicator',
            'accomplishment' => 'Fully accomplished ahead of schedule',
        ]);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '80%']);

        $fresh = OpcrIndicator::with('actuals')->find($indicator->id);

        $this->assertSame('Fully accomplished ahead of schedule', $fresh->displayed_accomplishment);
    }

    public function test_setting_is_a_singleton_auto_created_on_first_access(): void
    {
        $this->assertEquals(0, OpcrSetting::count());

        $first = OpcrSetting::current();
        $first->update(['campus_director_name' => 'RAMIL A. SANCHEZ']);

        $second = OpcrSetting::current();

        $this->assertEquals(1, OpcrSetting::count());
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals('RAMIL A. SANCHEZ', $second->campus_director_name);
    }
}
