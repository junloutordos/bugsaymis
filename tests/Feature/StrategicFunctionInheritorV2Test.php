<?php

namespace Tests\Feature;

use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\PM2\OpcrTemplate;
use App\Models\PM2\OpcrTemplateItem;
use App\Models\User;
use App\Services\PerformanceManagementV2\StrategicFunctionInheritorV2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicFunctionInheritorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_inherits_every_template_item_read_only(): void
    {
        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026, 'is_current' => true]);
        $template = OpcrTemplate::create(['ipcr_rating_period_v2_id' => $period->id, 'is_current' => true]);
        OpcrTemplateItem::create([
            'opcr_template_id' => $template->id, 'strategy_label' => 'Strategy 1',
            'output_outcome' => 'STEM secondary education', 'target' => '95%', 'weight_percent' => 30,
        ]);

        $ipcr = EmployeeIpcrV2::create([
            'user_id' => User::factory()->create()->id, 'rating_period_id' => $period->id,
            'title' => 'Test', 'status' => 'New Target',
        ]);

        $created = app(StrategicFunctionInheritorV2::class)->inherit($ipcr);

        $this->assertEquals(1, $created);
        $row = $ipcr->rows()->where('function_type', 'strategic')->first();
        $this->assertEquals('95%', $row->individual_target);
        $this->assertEquals('30.00', $row->weight_percent);

        // Calling again does not duplicate
        app(StrategicFunctionInheritorV2::class)->inherit($ipcr);
        $this->assertCount(1, $ipcr->rows()->where('function_type', 'strategic')->get());
    }
}
