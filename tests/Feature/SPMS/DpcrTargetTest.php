<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\Dpcr;
use App\Models\SPMS\DpcrTarget;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DpcrTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_dpcr_and_performance_indicator(): void
    {
        $dpcr = Dpcr::factory()->create();
        $indicator = PerformanceIndicator::factory()->create();
        $target = DpcrTarget::factory()->create([
            'dpcr_id' => $dpcr->id,
            'spms_performance_indicator_id' => $indicator->id,
        ]);

        $this->assertSame($dpcr->id, $target->dpcr->id);
        $this->assertSame($indicator->id, $target->performanceIndicator->id);
    }

    public function test_quarterly_actuals_and_rating_are_nullable(): void
    {
        $target = DpcrTarget::factory()->create();

        $this->assertNull($target->q1_actual);
        $this->assertNull($target->rating_avg);
    }
}
