<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_roles_and_grant_sos_respond(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(SosPermissionSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'DRRM Coordinator']);
        $this->assertDatabaseHas('roles', ['name' => 'Security Guard']);

        $security = Role::where('name', 'Security Guard')->first();
        $user = User::factory()->create();
        $user->roles()->attach($security->id);

        $this->assertTrue($user->fresh()->hasPermission('sos.respond'));
        $this->assertFalse($user->fresh()->hasPermission('sos.manage'));
    }
}
