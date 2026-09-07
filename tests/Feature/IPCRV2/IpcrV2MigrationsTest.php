<?php

namespace Tests\Feature\IPCRV2;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IpcrV2MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipcr_v2_records_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_records', [
            'id', 'user_id', 'rating_period_id', 'status',
            'submitted_for_review_at', 'target_approved_at', 'submitted_for_rating_at',
            'submitted_rating_at', 'submitted_for_pmtreview_at', 'submitted_to_hr_at',
            'director_signed_at', 'director_signature',
            'final_numeric_rating', 'final_adjectival_rating',
        ]));
    }

    public function test_ipcr_v2_records_has_workflow_polish_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_records', [
            'remarks', 'locked_at', 'locked_by_id', 'reopened_at', 'reopened_by_id',
            'reopen_reason', 'comments_recommendations',
        ]));
    }

    public function test_ipcr_v2_core_items_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_core_items', [
            'id', 'ipcr_v2_id', 'employee_function_id', 'label', 'weight_percent',
            'target', 'actual_accomplishment',
            'student_feedback_rating', 'supervisor_feedback_rating',
            'im_development_rating', 'timeliness_rating', 'row_average', 'remarks',
        ]));
    }

    public function test_ipcr_v2_support_items_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_support_items', [
            'id', 'ipcr_v2_id', 'employee_function_id', 'label',
            'actual_accomplishment', 'mov_link',
            'quality_rating', 'efficiency_rating', 'timeliness_rating', 'row_average', 'remarks',
        ]));
    }

    public function test_ipcr_v2_items_have_self_rating_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_core_items', [
            'self_quality_rating', 'self_efficiency_rating', 'self_student_feedback_rating',
            'self_supervisor_feedback_rating', 'self_im_development_rating',
            'self_timeliness_rating', 'self_row_average',
        ]));
        $this->assertTrue(Schema::hasColumns('ipcr_v2_support_items', [
            'self_quality_rating', 'self_efficiency_rating', 'self_timeliness_rating', 'self_row_average',
        ]));
    }

    public function test_ipcr_v2_coaching_sessions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_coaching_sessions', [
            'id', 'ipcr_v2_id', 'activity_type', 'mechanism', 'meeting_date',
            'channel_memo', 'channel_others', 'remarks',
            'conducted_by_name', 'conducted_at', 'noted_by_name', 'noted_at',
        ]));
    }
}
