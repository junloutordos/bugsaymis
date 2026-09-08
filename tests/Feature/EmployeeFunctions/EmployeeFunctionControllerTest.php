<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\AgencyOutcome;
use App\Models\EmployeeFunction;
use App\Models\Office;
use App\Models\Permission;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_index_exposes_current_year_work_distribution_plans_for_tagging(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $currentYear = (int) now()->format('Y');

        $outcome = AgencyOutcome::create(['outcome' => 'Core Functions']);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'x']);
        $currentPlan = WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => 'Current Year Plan',
            'fiscal_year' => $currentYear,
        ]);
        WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => 'Old Year Plan',
            'fiscal_year' => $currentYear - 5,
        ]);

        $this->actingAs($manager)->get(route('employee-functions.index', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/EmployeeFunctions')
                ->has('workDistributionPlans', 1)
                ->where('workDistributionPlans.0.id', $currentPlan->id)
            );
    }

    public function test_index_exposes_scope_options_for_bulk_assign(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['position' => 'Teacher III']);
        Office::create(['name' => 'GSU']);

        $this->actingAs($manager)->get(route('employee-functions.index', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/EmployeeFunctions')
                ->has('scopeOptions.roles')
                ->has('scopeOptions.offices')
                ->has('scopeOptions.divisions')
                ->has('scopeOptions.empCategories')
                ->where('scopeOptions.positions', fn ($positions) => collect($positions)->contains('Teacher III'))
                ->where('scopeOptions.allEmployees', fn ($employees) => collect($employees)->contains('id', $employee->id))
            );
    }

    private function makePlan(string $label, ?int $fiscalYear = null): WorkDistributionPlan
    {
        $outcome = AgencyOutcome::create(['outcome' => 'Core Functions ' . $label]);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'x']);

        return WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => $label,
            'fiscal_year' => $fiscalYear,
        ]);
    }

    public function test_store_creates_a_support_function_tagged_to_a_wdp(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $plan = $this->makePlan('x');

        $response = $this->actingAs($manager)->post(route('employee-functions.store', $employee), [
            'function_type' => 'support',
            'work_distribution_plan_ids' => [$plan->id],
            'label' => 'Member, Discipline Committee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $employee->id,
            'function_type' => 'support',
            'source_type' => 'wdp',
            'label' => 'Member, Discipline Committee',
        ]);
        $function = EmployeeFunction::where('user_id', $employee->id)->first();
        $this->assertSame([$plan->id], $function->workDistributionPlans()->pluck('work_distribution_plans.id')->all());
    }

    public function test_store_creates_a_core_function_tagged_to_multiple_wdps(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $planA = $this->makePlan('A');
        $planB = $this->makePlan('B');

        $response = $this->actingAs($manager)->post(route('employee-functions.store', $employee), [
            'function_type' => 'core',
            'work_distribution_plan_ids' => [$planA->id, $planB->id],
            'label' => 'Multi-tagged Core Function',
        ]);

        $response->assertRedirect();
        $function = EmployeeFunction::where('label', 'Multi-tagged Core Function')->firstOrFail();
        $this->assertSame('wdp', $function->source_type);
        $this->assertEqualsCanonicalizing(
            [$planA->id, $planB->id],
            $function->workDistributionPlans()->pluck('work_distribution_plans.id')->all()
        );
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
        $function = EmployeeFunction::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('manual', $function->source_type);
        $this->assertSame('Teaches Grade 11 Physics', $function->label);
        $this->assertCount(0, $function->workDistributionPlans);
    }

    public function test_update_replaces_wdp_tags_and_relabels(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $planA = $this->makePlan('A');
        $planB = $this->makePlan('B');
        $function = EmployeeFunction::create([
            'user_id' => $employee->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Old label',
        ]);
        $function->workDistributionPlans()->sync([$planA->id]);

        $response = $this->actingAs($manager)->put(route('employee-functions.update', [$employee, $function]), [
            'label' => 'New label',
            'work_distribution_plan_ids' => [$planB->id],
        ]);

        $response->assertRedirect();
        $function->refresh();
        $this->assertSame('New label', $function->label);
        $this->assertSame('wdp', $function->source_type);
        $this->assertSame([$planB->id], $function->workDistributionPlans()->pluck('work_distribution_plans.id')->all());
    }

    public function test_update_can_clear_wdp_tags_back_to_manual(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $plan = $this->makePlan('x');
        $function = EmployeeFunction::create([
            'user_id' => $employee->id, 'function_type' => 'support', 'source_type' => 'wdp', 'label' => 'x',
        ]);
        $function->workDistributionPlans()->sync([$plan->id]);

        $this->actingAs($manager)->put(route('employee-functions.update', [$employee, $function]), [
            'label' => 'x',
        ])->assertRedirect();

        $function->refresh();
        $this->assertSame('manual', $function->source_type);
        $this->assertCount(0, $function->workDistributionPlans);
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
