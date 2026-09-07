<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\EmployeeFunction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'HR']);
        $permission = Permission::firstOrCreate(['name' => 'employee_functions.manage'], ['module' => 'Employee Functions', 'description' => 'x']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_store_creates_a_manual_support_function_tagged_to_a_wdp(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $outcome = \App\Models\AgencyOutcome::create(['outcome' => 'Core Functions']);
        $indicator = \App\Models\PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'x']);
        $plan = WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => 'x',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ]);

        $response = $this->actingAs($manager)->post(route('employee-functions.store', $employee), [
            'function_type' => 'support',
            'work_distribution_plan_id' => $plan->id,
            'label' => 'Member, Discipline Committee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $employee->id,
            'function_type' => 'support',
            'source_type' => 'wdp',
            'work_distribution_plan_id' => $plan->id,
            'label' => 'Member, Discipline Committee',
        ]);
    }

    public function test_store_creates_a_manual_core_function_without_a_wdp(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();

        $response = $this->actingAs($manager)->post(route('employee-functions.store', $employee), [
            'function_type' => 'core',
            'label' => 'Teaches Grade 11 Physics',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $employee->id,
            'function_type' => 'core',
            'source_type' => 'manual',
            'work_distribution_plan_id' => null,
            'label' => 'Teaches Grade 11 Physics',
        ]);
    }

    public function test_destroy_removes_a_function_row(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $function = EmployeeFunction::create([
            'user_id' => $employee->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'x',
        ]);

        $response = $this->actingAs($manager)->delete(route('employee-functions.destroy', [$employee, $function]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('employee_functions', ['id' => $function->id]);
    }

    public function test_non_manager_cannot_access(): void
    {
        $employee = User::factory()->create();
        $someUser = User::factory()->create();

        $this->actingAs($someUser)->get(route('employee-functions.index', $employee))->assertForbidden();
    }
}
