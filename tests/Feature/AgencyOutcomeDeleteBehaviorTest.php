<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\PerformanceIndicator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeDeleteBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_restricts_deleting_an_outcome_still_referenced_by_a_performance_indicator(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Test indicator',
            'target' => '100%',
        ]);

        $this->expectException(QueryException::class);

        $outcome->delete();
    }
}
