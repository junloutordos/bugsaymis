<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): User
    {
        $role = Role::create(['name' => 'Faculty']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_generate_targets_creates_an_ipcr_v2_record(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $employee->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.generateTargets'), [
            'rating_period_id' => $period->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ipcr_v2_records', ['user_id' => $employee->id, 'rating_period_id' => $period->id]);
    }

    public function test_a_random_faculty_cannot_view_an_unrelated_employees_ipcr_v2(): void
    {
        $employee = $this->employee();
        $other = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $other->id, 'rating_period_id' => $period->id]);

        // Ownership check happens on both show() and every mutating route —
        // an unrelated Faculty holds ipcr.v2.view (it's a base employee
        // permission) but is neither the owner nor in the supervisor chain.
        $this->actingAs($employee)->get(route('employee-ipcr-v2.show', $record->id))->assertForbidden();

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id));
        $response->assertForbidden();
    }

    public function test_owner_can_view_their_own_ipcr_v2(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $this->actingAs($employee)->get(route('employee-ipcr-v2.show', $record->id))->assertOk();
    }

    public function test_show_exposes_supervisor_ocd_user_and_summary(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->get(route('employee-ipcr-v2.show', $record->id));

        $response->assertInertia(fn ($page) => $page
            ->has('ocdUser')
            ->has('summary')
            ->has('summary.strategic')
            ->has('summary.core')
            ->has('summary.support')
        );
    }

    public function test_owner_can_delete_a_new_target_record(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->delete(route('employee-ipcr-v2.destroy', $record->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('ipcr_v2_records', ['id' => $record->id]);
    }

    public function test_cannot_delete_a_record_once_submitted_for_review(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        ]);

        $response = $this->actingAs($employee)->delete(route('employee-ipcr-v2.destroy', $record->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('ipcr_v2_records', ['id' => $record->id]);
    }

    public function test_owner_can_sync_functions_added_after_generation(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        EmployeeFunction::create(['user_id' => $employee->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Discipline Committee']);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.syncFunctions', $record->id));

        $response->assertRedirect();
        $this->assertCount(1, $record->fresh()->supportItems);
    }

    public function test_cannot_sync_functions_on_someone_elses_ipcr_v2(): void
    {
        $employee = $this->employee();
        $other = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $other->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.syncFunctions', $record->id));

        $response->assertForbidden();
    }

    public function test_owner_can_set_a_mov_link_on_a_core_item(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
            'actual_accomplishment' => 'x', 'mov_link' => 'https://drive.example/report.pdf',
        ]);

        $response->assertRedirect();
        $this->assertSame('https://drive.example/report.pdf', $coreItem->fresh()->mov_link);
    }

    public function test_target_only_fields_are_accepted_while_targets_are_still_editable(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
            'target' => 'Maintain uptime', 'actual_accomplishment' => 'x', 'mov_link' => 'https://drive.example/report.pdf',
        ]);

        $response->assertRedirect();
        $fresh = $coreItem->fresh();
        $this->assertSame('Maintain uptime', $fresh->target);
        $this->assertNull($fresh->actual_accomplishment);
        $this->assertNull($fresh->mov_link);
    }

    public function test_target_is_locked_once_targets_are_approved(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100, 'target' => 'Original target']);

        $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
            'target' => 'Changed target', 'actual_accomplishment' => 'Did the thing',
        ]);

        $fresh = $coreItem->fresh();
        $this->assertSame('Original target', $fresh->target);
        $this->assertSame('Did the thing', $fresh->actual_accomplishment);
    }

    public function test_owner_can_set_a_target_on_a_support_item(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $supportItem = $record->supportItems()->create(['label' => 'Administrative']);

        $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateSupportItem', [$record->id, $supportItem->id]), [
            'target' => '100% compliance with DTR submission deadlines',
        ]);

        $response->assertRedirect();
        $this->assertSame('100% compliance with DTR submission deadlines', $supportItem->fresh()->target);
    }

    public function test_submit_for_review_requires_correct_pin_when_pin_is_set(): void
    {
        $employee = $this->employee();
        $employee->update(['signature_pin' => \Illuminate\Support\Facades\Hash::make('123456')]);
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $wrong = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id), ['pin' => '000000']);
        $wrong->assertSessionHasErrors('pin');
        $this->assertSame('New Target', $record->fresh()->status);

        $right = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id), ['pin' => '123456']);
        $right->assertRedirect();
        $this->assertSame('For Review', $record->fresh()->status);
    }

    public function test_submit_for_review_succeeds_without_pin_when_no_pin_set(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id));
        $response->assertRedirect();
        $this->assertSame('For Review', $record->fresh()->status);
    }

    public function test_submit_for_review_rejects_when_a_core_item_is_missing_a_target(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id));

        $response->assertSessionHasErrors('target');
        $this->assertSame('New Target', $record->fresh()->status);
    }

    public function test_submit_for_review_succeeds_once_all_items_have_a_target(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100, 'target' => 'Maintain uptime']);
        $record->supportItems()->create(['label' => 'Administrative', 'target' => '100% compliance']);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id));

        $response->assertRedirect();
        $this->assertSame('For Review', $record->fresh()->status);
    }

    public function test_owner_can_save_a_self_rating_on_a_wdp_tagged_core_item(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100, 'success_indicator' => 'Systems maintained']);

        $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
            'self_quality_rating' => 4, 'self_efficiency_rating' => 5, 'self_timeliness_rating' => 3,
        ]);

        $response->assertRedirect();
        $fresh = $coreItem->fresh();
        $this->assertSame(4, $fresh->self_quality_rating);
        $this->assertSame('4.00', $fresh->self_row_average);
    }

    public function test_owner_can_save_a_self_rating_on_the_csc_teaching_rubric(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $coreItem = $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
            'self_student_feedback_rating' => 5, 'self_supervisor_feedback_rating' => 4,
            'self_im_development_rating' => 4, 'self_timeliness_rating' => 5,
        ]);

        $response->assertRedirect();
        $fresh = $coreItem->fresh();
        // 5*0.30 + 4*0.20 + 4*0.20 + 5*0.30 = 1.5 + 0.8 + 0.8 + 1.5 = 4.60
        $this->assertSame('4.60', $fresh->self_row_average);
    }

    public function test_self_row_average_stays_null_until_all_criteria_are_present(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100, 'success_indicator' => 'Systems maintained']);

        $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
            'self_quality_rating' => 4,
        ]);

        $this->assertNull($coreItem->fresh()->self_row_average);
    }

    public function test_owner_can_save_a_self_rating_on_a_support_item(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $supportItem = $record->supportItems()->create(['label' => 'Administrative']);

        $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateSupportItem', [$record->id, $supportItem->id]), [
            'self_quality_rating' => 3, 'self_efficiency_rating' => 3, 'self_timeliness_rating' => 3,
        ]);

        $response->assertRedirect();
        $this->assertSame('3.00', $supportItem->fresh()->self_row_average);
    }

    public function test_submit_for_rating_rejects_when_an_item_is_missing_a_self_rating(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitRating', $record->id));

        $response->assertSessionHasErrors('self_rating');
        $this->assertSame('Targets Approved', $record->fresh()->status);
    }

    public function test_submit_for_rating_succeeds_once_all_items_have_a_self_rating(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
        ]);
        $record->coreItems()->create([
            'label' => 'IT Management', 'weight_percent' => 100, 'success_indicator' => 'x',
            'self_quality_rating' => 4, 'self_efficiency_rating' => 4, 'self_timeliness_rating' => 4, 'self_row_average' => 4.00,
        ]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitRating', $record->id));

        $response->assertRedirect();
        $this->assertSame('Submitted for Rating', $record->fresh()->status);
    }
}
