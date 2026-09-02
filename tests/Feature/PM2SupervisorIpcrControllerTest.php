<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\PM2\EmployeeIpcrPlanV2;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PerformanceManagementV2\IpcrWorkflowServiceV2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PM2SupervisorIpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_chief_can_approve_targets_when_weights_are_valid(): void
    {
        $chief = User::factory()->create();
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
        $employee = User::factory()->create(['division_id' => $division->id]);

        $permission = Permission::firstOrCreate(['name' => 'ipcr.v2.approve'], ['module' => 'PM V2', 'description' => 'ipcr.v2.approve']);
        $role = Role::create(['name' => 'PmV2Approver_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $chief->roles()->attach($role->id);

        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'title' => 'Test', 'status' => IpcrWorkflowServiceV2::STATUS_NEW_TARGET,
        ]);
        EmployeeIpcrPlanV2::create(['ipcr_id' => $ipcr->id, 'function_type' => 'strategic', 'weight_percent' => 30]);
        EmployeeIpcrPlanV2::create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'weight_percent' => 50]);
        EmployeeIpcrPlanV2::create(['ipcr_id' => $ipcr->id, 'function_type' => 'support', 'weight_percent' => 20]);

        $response = $this->actingAs($chief)->post(route('pm2.supervisor-ipcr.approveTargets', $ipcr->id));

        $response->assertRedirect();
        $this->assertEquals(IpcrWorkflowServiceV2::STATUS_TARGETS_APPROVED, $ipcr->fresh()->status);
    }

    public function test_approve_targets_fails_when_weights_do_not_sum_correctly(): void
    {
        $chief = User::factory()->create();
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
        $employee = User::factory()->create(['division_id' => $division->id]);

        $permission = Permission::firstOrCreate(['name' => 'ipcr.v2.approve'], ['module' => 'PM V2', 'description' => 'ipcr.v2.approve']);
        $role = Role::create(['name' => 'PmV2Approver_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $chief->roles()->attach($role->id);

        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'title' => 'Test', 'status' => IpcrWorkflowServiceV2::STATUS_NEW_TARGET,
        ]);
        EmployeeIpcrPlanV2::create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'weight_percent' => 40]);

        $response = $this->actingAs($chief)->post(route('pm2.supervisor-ipcr.approveTargets', $ipcr->id));

        $response->assertSessionHasErrors();
        $this->assertEquals(IpcrWorkflowServiceV2::STATUS_NEW_TARGET, $ipcr->fresh()->status);
    }
}
