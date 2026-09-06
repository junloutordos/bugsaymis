<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillOpcrFromPerformanceIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_every_pre_existing_strategic_performance_indicator(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $core = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);
        $strategicPi = PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'fiscal_year' => 2026,
        ]);
        $corePi = PerformanceIndicator::create([
            'agency_outcome_id' => $core->id,
            'description' => 'Daily attendance logging',
            'target' => '100%',
        ]);

        $this->artisan('opcr:backfill-from-performance-indicators')->assertExitCode(0);

        $this->assertDatabaseHas('opcr_indicators', [
            'performance_indicator_id' => $strategicPi->id,
            'description' => 'Cohort survival rate',
        ]);
        $this->assertSame(0, OpcrIndicator::where('performance_indicator_id', $corePi->id)->count());
        $this->assertSame(1, OpcrIndicator::count());
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'fiscal_year' => 2026,
        ]);

        $this->artisan('opcr:backfill-from-performance-indicators')->assertExitCode(0);
        $this->artisan('opcr:backfill-from-performance-indicators')->assertExitCode(0);

        $this->assertSame(1, OpcrIndicator::count());
    }
}
