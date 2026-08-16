<?php

namespace Tests\Feature\SPMS;

use App\Models\Division;
use App\Models\SPMS\Outcome;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_belongs_to_outcome(): void
    {
        $outcome = Outcome::factory()->create(['function_type' => 'core']);
        $indicator = PerformanceIndicator::factory()->create(['spms_outcome_id' => $outcome->id]);

        $this->assertSame($outcome->id, $indicator->outcome->id);
        $this->assertTrue($outcome->indicators->contains('id', $indicator->id));
    }

    public function test_indicator_attaches_to_multiple_divisions(): void
    {
        $indicator = PerformanceIndicator::factory()->create();
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $indicator->divisions()->attach([$divisionA->id, $divisionB->id]);

        $this->assertCount(2, $indicator->fresh()->divisions);
    }
}
