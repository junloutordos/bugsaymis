<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\EmployeeFunctionsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionsPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permission_and_grants_it_to_hr_role(): void
    {
        $hr = Role::create(['name' => 'HR']);

        (new EmployeeFunctionsPermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'employee_functions.manage']);
        $permission = Permission::where('name', 'employee_functions.manage')->first();
        $this->assertTrue($hr->fresh()->permissions->contains($permission));
    }

    public function test_seeder_is_idempotent(): void
    {
        Role::create(['name' => 'HR']);

        (new EmployeeFunctionsPermissionSeeder())->run();
        (new EmployeeFunctionsPermissionSeeder())->run();

        $this->assertSame(1, Permission::where('name', 'employee_functions.manage')->count());
    }
}
