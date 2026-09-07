<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsmFeedbackAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function grantCsmView(string $roleName): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'csm.view'],
            ['module' => 'CSM', 'description' => 'View CSM Feedback dashboard, response list, and export reports'],
        );
        $role = Role::firstOrCreate(['name' => $roleName]);
        $role->permissions()->syncWithoutDetaching($permission);
    }

    public function test_user_with_csm_view_permission_can_access_dashboard(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');

        $response = $this->actingAs($user)->get(route('csm.dashboard'));

        $response->assertOk();
    }

    public function test_user_with_csm_view_permission_can_access_list(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');

        $response = $this->actingAs($user)->get(route('csm.list'));

        $response->assertOk();
    }

    public function test_user_without_csm_view_permission_is_forbidden(): void
    {
        $user = $this->userWithRole('Faculty');

        $response = $this->actingAs($user)->get(route('csm.dashboard'));

        $response->assertForbidden();
    }

    public function test_administrator_bypasses_permission_check(): void
    {
        $user = $this->userWithRole('Administrator');

        $response = $this->actingAs($user)->get(route('csm.dashboard'));

        $response->assertOk();
    }
}
