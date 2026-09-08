<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\Division;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeFunctionScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionScopeResolverTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeFunctionScopeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new EmployeeFunctionScopeResolver();
    }

    public function test_all_scope_returns_active_employees_and_excludes_inactive(): void
    {
        $active = User::factory()->create(['status' => 'active']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        $result = $this->resolver->resolve('all', [], []);

        $this->assertTrue($result->contains('id', $active->id));
        $this->assertFalse($result->contains('id', $inactive->id));
    }

    public function test_all_scope_excludes_student_and_parent_accounts(): void
    {
        $studentRole = Role::create(['name' => 'Student']);
        $employee = User::factory()->create();
        $student = User::factory()->create();
        $student->roles()->attach($studentRole->id);

        $result = $this->resolver->resolve('all', [], []);

        $this->assertTrue($result->contains('id', $employee->id));
        $this->assertFalse($result->contains('id', $student->id));
    }

    public function test_selected_scope_returns_only_the_given_user_ids(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        $result = $this->resolver->resolve('selected', [$a->id, $b->id], []);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $result->pluck('id')->all());
        $this->assertFalse($result->contains('id', $c->id));
    }

    public function test_filtered_scope_matches_on_position(): void
    {
        $teacher = User::factory()->create(['position' => 'Teacher III']);
        $aide = User::factory()->create(['position' => 'Administrative Aide IV']);

        $result = $this->resolver->resolve('filtered', [], ['position' => ['Teacher III']]);

        $this->assertEqualsCanonicalizing([$teacher->id], $result->pluck('id')->all());
        $this->assertFalse($result->contains('id', $aide->id));
    }

    public function test_filtered_scope_ors_multiple_values_within_one_dimension(): void
    {
        $officeA = Office::create(['name' => 'Office A']);
        $officeB = Office::create(['name' => 'Office B']);
        $officeC = Office::create(['name' => 'Office C']);
        $inA = User::factory()->create(['office_id' => $officeA->id]);
        $inB = User::factory()->create(['office_id' => $officeB->id]);
        $inC = User::factory()->create(['office_id' => $officeC->id]);

        $result = $this->resolver->resolve('filtered', [], ['office_id' => [$officeA->id, $officeB->id]]);

        $this->assertEqualsCanonicalizing([$inA->id, $inB->id], $result->pluck('id')->all());
        $this->assertFalse($result->contains('id', $inC->id));
    }

    public function test_filtered_scope_ands_across_dimensions(): void
    {
        $division = Division::create(['division_name' => 'CID', 'status' => 'active']);
        $matches = User::factory()->create(['position' => 'Teacher III', 'division_id' => $division->id]);
        $wrongPosition = User::factory()->create(['position' => 'Administrative Aide IV', 'division_id' => $division->id]);
        $wrongDivision = User::factory()->create(['position' => 'Teacher III', 'division_id' => null]);

        $result = $this->resolver->resolve('filtered', [], [
            'position' => ['Teacher III'],
            'division_id' => [$division->id],
        ]);

        $this->assertEqualsCanonicalizing([$matches->id], $result->pluck('id')->all());
    }

    public function test_filtered_scope_matches_on_role_and_emp_category(): void
    {
        $role = Role::create(['name' => 'Faculty']);
        $match = User::factory()->create(['role_id' => $role->id, 'emp_category' => 'Plantilla Teaching']);
        $wrongCategory = User::factory()->create(['role_id' => $role->id, 'emp_category' => 'COS Teaching']);

        $result = $this->resolver->resolve('filtered', [], [
            'role_id' => [$role->id],
            'emp_category' => ['Plantilla Teaching'],
        ]);

        $this->assertEqualsCanonicalizing([$match->id], $result->pluck('id')->all());
    }
}
