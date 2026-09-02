<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\PerformanceIndicator;
use App\Models\WorkDistributionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmV2WorkDistributionPlanWeightTest extends TestCase
{
    use RefreshDatabase;

    public function test_weight_and_rating_scale_columns_are_mass_assignable(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Teach Math 1']);

        $plan = WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'weight_percent'           => 21.74,
            'rating_scale_quality'     => '5-96-100%',
        ]);

        $this->assertEquals('21.74', $plan->fresh()->weight_percent);
        $this->assertEquals('5-96-100%', $plan->fresh()->rating_scale_quality);
    }
}
