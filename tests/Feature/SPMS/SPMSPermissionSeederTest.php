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
        $ocd = Role::create(['name' => 'OCD']);
        $ed = Role::create(['name' => 'Executive Director']);

        (new SPMSPermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'spms.ipcr.manage']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.ipcr.review']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.admin.manage']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.dpcr.manage']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.dpcr.review']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.dpcr.approve']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.opcr.manage']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.opcr.approve']);

        $manage = Permission::where('name', 'spms.ipcr.manage')->first();
        $this->assertTrue($faculty->fresh()->permissions->contains($manage));

        $review = Permission::where('name', 'spms.ipcr.review')->first();
        $this->assertTrue($dc->fresh()->permissions->contains($review));

        $dpcrManage = Permission::where('name', 'spms.dpcr.manage')->first();
        $this->assertTrue($dc->fresh()->permissions->contains($dpcrManage));

        $dpcrReview = Permission::where('name', 'spms.dpcr.review')->first();
        $dpcrApprove = Permission::where('name', 'spms.dpcr.approve')->first();
        $this->assertTrue($ocd->fresh()->permissions->contains($dpcrReview));
        $this->assertTrue($ocd->fresh()->permissions->contains($dpcrApprove));

        $opcrManage = Permission::where('name', 'spms.opcr.manage')->first();
        $this->assertTrue($ocd->fresh()->permissions->contains($opcrManage));

        $opcrApprove = Permission::where('name', 'spms.opcr.approve')->first();
        $this->assertTrue($ed->fresh()->permissions->contains($opcrApprove));
    }

    public function test_is_idempotent(): void
    {
        Role::create(['name' => 'Faculty']);

        (new SPMSPermissionSeeder())->run();
        (new SPMSPermissionSeeder())->run();

        $this->assertSame(1, Permission::where('name', 'spms.ipcr.manage')->count());
        $this->assertSame(1, Permission::where('name', 'spms.dpcr.manage')->count());
        $this->assertSame(1, Permission::where('name', 'spms.opcr.manage')->count());
    }
}
