<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetEmployeeInfoTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetEmployeeInfoToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_employee_profile_for_a_user_in_the_requesters_division(): void
    {
        $division = Division::factory()->create();
        $employee = User::factory()->create([
            'name' => 'Jane Employee', 'division_id' => $division->id, 'position' => 'Teacher III',
            'salary_grade' => 15, 'salary_step' => 3, 'status' => 'active',
        ]);

        $chief = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'hr.employees.manage'], ['module' => 'HR', 'description' => 'x'])
        );
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'atlas.dyna.access'], ['module' => 'Atlas', 'description' => 'x'])
        );

        $result = (new GetEmployeeInfoTool())->execute($chief, ['identifier' => 'Jane Employee']);

        $this->assertEquals('Jane Employee', $result['name']);
        $this->assertEquals('Teacher III', $result['position']);
        $this->assertEquals(15, $result['salary_grade']);
    }

    public function test_returns_not_found_for_an_employee_outside_the_requesters_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        User::factory()->create(['name' => 'Other Division Employee', 'division_id' => $divisionB->id]);

        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $divisionA->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'hr.employees.manage'], ['module' => 'HR', 'description' => 'x'])
        );

        $result = (new GetEmployeeInfoTool())->execute($chief, ['identifier' => 'Other Division Employee']);

        $this->assertArrayHasKey('note', $result);
        $this->assertEmpty($result['employee'] ?? null);
    }
}
