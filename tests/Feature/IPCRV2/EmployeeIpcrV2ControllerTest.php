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
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
            'target' => 'x', 'actual_accomplishment' => 'x', 'mov_link' => 'https://drive.example/report.pdf',
        ]);

        $response->assertRedirect();
        $this->assertSame('https://drive.example/report.pdf', $coreItem->fresh()->mov_link);
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
}
