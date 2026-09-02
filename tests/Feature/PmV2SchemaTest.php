<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PmV2SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pm_v2_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('ipcr_rating_periods_v2'));
        $this->assertTrue(Schema::hasColumns('ipcr_rating_periods_v2', [
            'label', 'year', 'semester', 'start_date', 'end_date', 'status', 'is_current',
        ]));

        $this->assertTrue(Schema::hasTable('opcr_templates'));
        $this->assertTrue(Schema::hasColumns('opcr_templates', ['ipcr_rating_period_v2_id', 'is_current']));

        $this->assertTrue(Schema::hasTable('opcr_template_items'));
        $this->assertTrue(Schema::hasColumns('opcr_template_items', [
            'opcr_template_id', 'strategy_label', 'output_outcome', 'success_indicator', 'target',
            'rating_scale_quality', 'rating_scale_efficiency', 'rating_scale_timeliness',
            'weight_percent', 'sort_order',
        ]));

        $this->assertTrue(Schema::hasTable('employee_ipcrs_v2'));
        $this->assertTrue(Schema::hasColumns('employee_ipcrs_v2', [
            'user_id', 'rating_period_id', 'title', 'status', 'remarks',
            'target_approved_at', 'submitted_for_rating_at', 'submitted_rating_at',
            'final_numeric_rating', 'final_adjectival_rating',
        ]));

        $this->assertTrue(Schema::hasTable('employee_ipcrs_plan_v2'));
        $this->assertTrue(Schema::hasColumns('employee_ipcrs_plan_v2', [
            'ipcr_id', 'function_type', 'weight_percent', 'plan_id', 'opcr_template_item_id',
            'individual_target', 'accomplishment', 'mov_link',
            'self_quality', 'self_efficiency', 'self_timeliness', 'self_average',
            'sup_quality', 'sup_efficiency', 'sup_timeliness', 'sup_average', 'remarks',
        ]));

        $this->assertTrue(Schema::hasColumns('work_distribution_plans', [
            'weight_percent', 'rating_scale_quality', 'rating_scale_efficiency', 'rating_scale_timeliness',
        ]));
    }
}
