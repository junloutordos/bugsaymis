<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\Opcr;
use App\Models\SPMS\OpcrTarget;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_opcr_and_performance_indicator(): void
    {
        $opcr = Opcr::factory()->create();
        $indicator = PerformanceIndicator::factory()->create();
        $target = OpcrTarget::factory()->create([
            'opcr_id' => $opcr->id,
            'spms_performance_indicator_id' => $indicator->id,
        ]);

        $this->assertSame($opcr->id, $target->opcr->id);
        $this->assertSame($indicator->id, $target->performanceIndicator->id);
    }

    public function test_quarterly_actuals_and_rating_are_nullable(): void
    {
        $target = OpcrTarget::factory()->create();

        $this->assertNull($target->q1_actual);
        $this->assertNull($target->rating_avg);
    }
}
