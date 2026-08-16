<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\SPMSPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SPMSPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_permissions_and_attaches_to_roles(): void
    {
        $faculty = Role::create(['name' => 'Faculty']);
        $dc = Role::create(['name' => 'DivisionChief']);

        (new SPMSPermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'spms.ipcr.manage']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.ipcr.review']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.admin.manage']);

        $manage = Permission::where('name', 'spms.ipcr.manage')->first();
        $this->assertTrue($faculty->fresh()->permissions->contains($manage));

        $review = Permission::where('name', 'spms.ipcr.review')->first();
        $this->assertTrue($dc->fresh()->permissions->contains($review));
    }

    public function test_is_idempotent(): void
    {
        Role::create(['name' => 'Faculty']);

        (new SPMSPermissionSeeder())->run();
        (new SPMSPermissionSeeder())->run();

        $this->assertSame(1, Permission::where('name', 'spms.ipcr.manage')->count());
    }
}
