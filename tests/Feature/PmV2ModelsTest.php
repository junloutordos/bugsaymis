<?php

namespace Tests\Feature;

use App\Models\PM2\EmployeeIpcrPlanV2;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\PM2\OpcrTemplate;
use App\Models\PM2\OpcrTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmV2ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_opcr_template_items_relate_to_their_template_in_sort_order(): void
    {
        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $template = OpcrTemplate::create(['ipcr_rating_period_v2_id' => $period->id, 'is_current' => true]);
        OpcrTemplateItem::create(['opcr_template_id' => $template->id, 'strategy_label' => 'Strategy 2', 'output_outcome' => 'B', 'sort_order' => 2]);
        OpcrTemplateItem::create(['opcr_template_id' => $template->id, 'strategy_label' => 'Strategy 1', 'output_outcome' => 'A', 'sort_order' => 1]);

        $this->assertEquals(['Strategy 1', 'Strategy 2'], $template->items->pluck('strategy_label')->all());
        $this->assertTrue($template->period->is($period));
    }

    public function test_employee_ipcr_v2_exposes_rows_and_mutability(): void
    {
        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026, 'status' => 'open']);
        $employee = User::factory()->create();
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => $employee->id,
            'rating_period_id' => $period->id,
            'title' => 'Test IPCR',
            'status' => 'New Target',
        ]);
        EmployeeIpcrPlanV2::create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'weight_percent' => 50]);

        $this->assertTrue($ipcr->user->is($employee));
        $this->assertTrue($ipcr->ratingPeriod->is($period));
        $this->assertCount(1, $ipcr->rows);
        $this->assertTrue($ipcr->isMutable());
        $this->assertFalse($ipcr->isFinalized());
    }
}
