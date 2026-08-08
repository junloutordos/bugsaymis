<?php

namespace Tests\Feature\Learn;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\LearnPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permissions_and_grants_them_to_expected_roles(): void
    {
        Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'AUH']);
        Role::create(['name' => 'CID Chief']);

        (new LearnPermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'learn.course.view.all']);
        $this->assertDatabaseHas('permissions', ['name' => 'learn.admin']);

        $viewAll = Permission::where('name', 'learn.course.view.all')->first();
        $admin = Role::where('name', 'Administrator')->first();
        $auh = Role::where('name', 'AUH')->first();

        $this->assertTrue($admin->permissions->contains($viewAll));
        $this->assertTrue($auh->permissions->contains($viewAll));
    }

    public function test_seeder_is_idempotent(): void
    {
        Role::create(['name' => 'Administrator']);

        (new LearnPermissionSeeder())->run();
        (new LearnPermissionSeeder())->run();

        $this->assertSame(1, Permission::where('name', 'learn.admin')->count());
    }
}
