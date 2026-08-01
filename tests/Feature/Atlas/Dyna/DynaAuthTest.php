<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DynaAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_dyna_access_can_log_in_and_receive_a_token(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $user->update(['password' => Hash::make('correct-password')]);

        $response = $this->postJson('/api/dyna/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['name', 'email']]);
    }

    public function test_a_user_without_dyna_access_is_denied(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->postJson('/api/dyna/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(403);
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
