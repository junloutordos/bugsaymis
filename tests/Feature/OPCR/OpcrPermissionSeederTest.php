<?php

namespace Tests\Feature\OPCR;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\OpcrPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permissions_and_grants_them_to_the_right_roles(): void
    {
        $ocd = Role::create(['name' => 'OCD']);
        $pmt = Role::create(['name' => 'PMT']);
        $divisionChief = Role::create(['name' => 'DivisionChief']);

        (new OpcrPermissionSeeder())->run();

        $view = Permission::where('name', 'opcr.view')->firstOrFail();
        $manage = Permission::where('name', 'opcr.manage')->firstOrFail();

        $this->assertTrue($ocd->fresh()->permissions->contains($view));
        $this->assertTrue($ocd->fresh()->permissions->contains($manage));
        $this->assertTrue($pmt->fresh()->permissions->contains($view));
        $this->assertTrue($pmt->fresh()->permissions->contains($manage));
        $this->assertTrue($divisionChief->fresh()->permissions->contains($view));
        $this->assertFalse($divisionChief->fresh()->permissions->contains($manage));
    }

    public function test_seeder_is_idempotent(): void
    {
        Role::create(['name' => 'OCD']);

        (new OpcrPermissionSeeder())->run();
        (new OpcrPermissionSeeder())->run();

        $this->assertEquals(1, Permission::where('name', 'opcr.view')->count());
    }
}
