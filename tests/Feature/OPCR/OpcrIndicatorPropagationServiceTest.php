<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use App\Services\OPCR\OpcrIndicatorPropagationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorPropagationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OpcrIndicatorPropagationService
    {
        return app(OpcrIndicatorPropagationService::class);
    }

    public function test_creates_an_opcr_indicator_for_a_strategic_functions_performance_indicator(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'budget' => 5000,
            'fiscal_year' => 2026,
        ]);
        $pi->divisions()->sync([$division->id]);

        $this->service()->syncFromPerformanceIndicator($pi->fresh());

        $indicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->first();
        $this->assertNotNull($indicator);
        $this->assertSame(2026, $indicator->fiscal_year);
        $this->assertSame($outcome->id, $indicator->agency_outcome_id);
        $this->assertSame('Cohort survival rate', $indicator->description);
        $this->assertSame('90%', $indicator->target);
        $this->assertEquals(5000, $indicator->budget);
        $this->assertTrue($indicator->divisions->contains($division));
    }

    public function test_does_not_create_an_opcr_indicator_for_a_core_functions_performance_indicator(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Daily attendance logging',
            'target' => '100%',
        ]);

        $this->service()->syncFromPerformanceIndicator($pi);

        $this->assertSame(0, OpcrIndicator::where('performance_indicator_id', $pi->id)->count());
    }

    public function test_falls_back_to_the_current_fiscal_year_when_the_performance_indicator_has_none(): void
    {
        IPCRRatingPeriod::create(['label' => 'FY 2028', 'year' => 2028, 'semester' => 1, 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Applies to all years',
            'target' => '100%',
            'fiscal_year' => null,
        ]);

        $this->service()->syncFromPerformanceIndicator($pi);

        $indicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->first();
        $this->assertNotNull($indicator);
        $this->assertSame(2028, $indicator->fiscal_year);
    }

    public function test_resolves_the_top_level_program_when_the_performance_indicator_is_tagged_to_a_child_outcome(): void
    {
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create([
            'outcome' => 'A. STEM',
            'sub_outcome' => 'A.1',
            'function_type' => 'Strategic Functions',
            'parent_id' => $parent->id,
        ]);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $child->id,
            'description' => 'Child-tagged indicator',
            'target' => '100%',
            'fiscal_year' => 2026,
        ]);

        $this->service()->syncFromPerformanceIndicator($pi);

        $indicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->first();
        $this->assertNotNull($indicator);
        $this->assertSame($parent->id, $indicator->agency_outcome_id);
    }

    public function test_keeps_syncing_fields_and_divisions_into_the_linked_opcr_indicator_on_update(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $divisionA = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $divisionB = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Original',
            'target' => '50%',
            'fiscal_year' => 2026,
        ]);
        $pi->divisions()->sync([$divisionA->id]);
        $this->service()->syncFromPerformanceIndicator($pi->fresh());

        $pi->update(['description' => 'Revised', 'target' => '75%', 'budget' => 1200]);
        $pi->divisions()->sync([$divisionB->id]);
        $this->service()->syncFromPerformanceIndicator($pi->fresh());

        $indicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->first();
        $this->assertSame('Revised', $indicator->description);
        $this->assertSame('75%', $indicator->target);
        $this->assertEquals(1200, $indicator->budget);
        $this->assertFalse($indicator->divisions->fresh()->contains($divisionA));
        $this->assertTrue($indicator->divisions->fresh()->contains($divisionB));
    }

    public function test_does_not_create_a_duplicate_opcr_indicator_when_synced_twice(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Indicator',
            'target' => '100%',
            'fiscal_year' => 2026,
        ]);

        $this->service()->syncFromPerformanceIndicator($pi);
        $this->service()->syncFromPerformanceIndicator($pi);

        $this->assertSame(1, OpcrIndicator::where('performance_indicator_id', $pi->id)->count());
    }

    public function test_creates_the_link_on_update_when_reassigned_to_a_strategic_program_after_creation(): void
    {
        $core = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $core->id,
            'description' => 'Indicator',
            'target' => '100%',
        ]);
        $this->service()->syncFromPerformanceIndicator($pi);
        $this->assertSame(0, OpcrIndicator::where('performance_indicator_id', $pi->id)->count());

        $pi->update(['agency_outcome_id' => $program->id]);
        $this->service()->syncFromPerformanceIndicator($pi->fresh());

        $this->assertSame(1, OpcrIndicator::where('performance_indicator_id', $pi->id)->count());
    }

    public function test_unlinks_but_keeps_the_opcr_indicator_when_reassigned_away_from_a_strategic_program(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $core = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Indicator',
            'target' => '100%',
            'fiscal_year' => 2026,
        ]);
        $this->service()->syncFromPerformanceIndicator($pi);
        $indicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->firstOrFail();

        $pi->update(['agency_outcome_id' => $core->id]);
        $this->service()->syncFromPerformanceIndicator($pi->fresh());

        $this->assertNotNull(OpcrIndicator::find($indicator->id));
        $this->assertNull($indicator->fresh()->performance_indicator_id);
    }

    public function test_unlink_from_performance_indicator_clears_the_link_without_deleting_the_row(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Indicator',
            'target' => '100%',
            'fiscal_year' => 2026,
        ]);
        $this->service()->syncFromPerformanceIndicator($pi);
        $indicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->firstOrFail();

        $this->service()->unlinkFromPerformanceIndicator($pi);

        $this->assertNotNull(OpcrIndicator::find($indicator->id));
        $this->assertNull($indicator->fresh()->performance_indicator_id);
    }
}
