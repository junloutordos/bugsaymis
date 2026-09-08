<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\EmployeeFunction;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionBulkAssignTest extends TestCase
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

    public function test_preview_scope_returns_count_only_for_all_scope(): void
    {
        $manager = $this->manager();
        User::factory()->count(3)->create();

        $response = $this->actingAs($manager)->post(route('employee-functions.preview-scope'), [
            'scope' => 'all',
        ]);

        $response->assertOk();
        // 3 created + the manager themself = 4 active employees
        $response->assertJson(['count' => 4]);
        $this->assertArrayNotHasKey('employees', $response->json());
    }

    public function test_preview_scope_returns_employee_list_for_selected_scope(): void
    {
        $manager = $this->manager();
        $a = User::factory()->create(['name' => 'Employee A']);
        $b = User::factory()->create(['name' => 'Employee B']);
        User::factory()->create(['name' => 'Employee C']);

        $response = $this->actingAs($manager)->post(route('employee-functions.preview-scope'), [
            'scope' => 'selected',
            'user_ids' => [$a->id, $b->id],
        ]);

        $response->assertOk();
        $response->assertJson(['count' => 2]);
        $this->assertEqualsCanonicalizing(
            ['Employee A', 'Employee B'],
            collect($response->json('employees'))->pluck('name')->all()
        );
    }

    public function test_preview_scope_returns_employee_list_for_filtered_scope(): void
    {
        $manager = $this->manager();
        $office = Office::create(['name' => 'GSU']);
        $match = User::factory()->create(['office_id' => $office->id]);
        User::factory()->create();

        $response = $this->actingAs($manager)->post(route('employee-functions.preview-scope'), [
            'scope' => 'filtered',
            'filters' => ['office_id' => [$office->id]],
        ]);

        $response->assertOk();
        $response->assertJson(['count' => 1]);
        $this->assertSame($match->id, $response->json('employees.0.id'));
    }

    public function test_bulk_store_creates_a_support_function_for_every_matched_employee(): void
    {
        $manager = $this->manager();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $response = $this->actingAs($manager)->post(route('employee-functions.bulk-store'), [
            'scope' => 'selected',
            'user_ids' => [$a->id, $b->id],
            'label' => 'Member, Discipline Committee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $a->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Member, Discipline Committee',
        ]);
        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $b->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Member, Discipline Committee',
        ]);
    }

    public function test_bulk_store_skips_employees_who_already_have_the_same_label(): void
    {
        $manager = $this->manager();
        $a = User::factory()->create();
        $b = User::factory()->create();
        EmployeeFunction::create([
            'user_id' => $a->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Member, Discipline Committee',
        ]);

        $this->actingAs($manager)->post(route('employee-functions.bulk-store'), [
            'scope' => 'selected',
            'user_ids' => [$a->id, $b->id],
            'label' => 'Member, Discipline Committee',
        ])->assertRedirect();

        $this->assertSame(1, EmployeeFunction::where('user_id', $a->id)->where('label', 'Member, Discipline Committee')->count());
        $this->assertDatabaseHas('employee_functions', ['user_id' => $b->id, 'label' => 'Member, Discipline Committee']);
    }

    public function test_bulk_store_matching_is_case_insensitive_for_dedupe(): void
    {
        $manager = $this->manager();
        $a = User::factory()->create();
        EmployeeFunction::create([
            'user_id' => $a->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'member, discipline committee',
        ]);

        $this->actingAs($manager)->post(route('employee-functions.bulk-store'), [
            'scope' => 'selected',
            'user_ids' => [$a->id],
            'label' => 'Member, Discipline Committee',
        ])->assertRedirect();

        $this->assertSame(1, EmployeeFunction::where('user_id', $a->id)->count());
    }

    public function test_bulk_store_requires_the_employee_functions_manage_permission(): void
    {
        $employee = User::factory()->create();
        $someUser = User::factory()->create();

        $this->actingAs($someUser)->post(route('employee-functions.bulk-store'), [
            'scope' => 'selected',
            'user_ids' => [$employee->id],
            'label' => 'x',
        ])->assertForbidden();
    }
}
