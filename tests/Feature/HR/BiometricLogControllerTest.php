<?php

namespace Tests\Feature\HR;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_only_the_monitor_permission_can_load_the_page(): void
    {
        $user = $this->userWithPermission('hr.biometric.monitor');

        $response = $this->actingAs($user)->get(route('hr.biometric.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('HR/Biometric/Index')
            ->where('canManage', false)
        );
    }

    public function test_a_user_with_the_manage_permission_can_load_the_page_and_gets_canmanage_true(): void
    {
        $user = $this->userWithPermission('hr.biometric.manage');

        $response = $this->actingAs($user)->get(route('hr.biometric.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('HR/Biometric/Index')
            ->where('canManage', true)
        );
    }

    public function test_a_user_with_neither_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('hr.biometric.index'));

        $response->assertForbidden();
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'HR', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'Biometric Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
