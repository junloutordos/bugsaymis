<?php

namespace Tests\Unit;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorOpcrSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_performance_indicator_directly_via_the_model_still_propagates_to_opcr(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);

        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'fiscal_year' => 2026,
        ]);

        $this->assertDatabaseHas('opcr_indicators', [
            'performance_indicator_id' => $pi->id,
            'description' => 'Cohort survival rate',
        ]);
    }

    public function test_deleting_a_performance_indicator_directly_via_the_model_unlinks_the_opcr_indicator(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Indicator',
            'target' => '90%',
            'fiscal_year' => 2026,
        ]);
        $opcrIndicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->firstOrFail();

        $pi->delete();

        $this->assertNotNull(OpcrIndicator::find($opcrIndicator->id));
        $this->assertNull($opcrIndicator->fresh()->performance_indicator_id);
    }

    public function test_sync_divisions_immediately_reflects_on_the_newly_created_opcr_indicator(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Indicator',
            'target' => '90%',
            'fiscal_year' => 2026,
        ]);

        $pi->syncDivisions([$division->id]);

        $opcrIndicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->firstOrFail();
        $this->assertTrue($opcrIndicator->divisions->contains($division));
    }
}
