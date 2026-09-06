<?php

namespace Tests\Feature\IPCRV2;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\IpcrV2PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_seven_permissions_and_grants_faculty_the_employee_set(): void
    {
        $faculty = Role::create(['name' => 'Faculty']);

        (new IpcrV2PermissionSeeder())->run();

        foreach (['view', 'create', 'update', 'submit', 'approve', 'monitor', 'admin'] as $suffix) {
            $this->assertDatabaseHas('permissions', ['name' => "ipcr.v2.{$suffix}"]);
        }

        $names = $faculty->fresh()->permissions->pluck('name')->all();
        $this->assertEqualsCanonicalizing(
            ['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit'],
            $names
        );
    }

    public function test_grants_division_chief_the_approve_set(): void
    {
        $dc = Role::create(['name' => 'DivisionChief']);

        (new IpcrV2PermissionSeeder())->run();

        $names = $dc->fresh()->permissions->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'], $names);
    }
}
