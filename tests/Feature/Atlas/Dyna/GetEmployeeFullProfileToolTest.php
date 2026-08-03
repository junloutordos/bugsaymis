<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetEmployeeFullProfileTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetEmployeeFullProfileToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_leave_section_when_permitted_and_omits_it_when_not(): void
    {
        $employee = User::factory()->create(['name' => 'Ana Reyes']);
        $leaveType = LeaveType::create(['code' => 'VL', 'name' => 'Vacation Leave']);
        LeaveCredit::create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
            'carried_over' => 0,
            'used' => 3,
            'forfeited' => 0,
            'monetized' => 0,
        ]);

        $permittedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage', 'hr.leave.credits.view']);
        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage']);

        $withLeave = (new GetEmployeeFullProfileTool())->execute($permittedUser, ['identifier' => 'Ana Reyes']);
        $withoutLeave = (new GetEmployeeFullProfileTool())->execute($restrictedUser, ['identifier' => 'Ana Reyes']);

        $this->assertArrayHasKey('leave', $withLeave);
        $this->assertEquals(12.0, (float) $withLeave['leave']['credits'][0]['balance']);
        $this->assertArrayNotHasKey('leave', $withoutLeave);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
