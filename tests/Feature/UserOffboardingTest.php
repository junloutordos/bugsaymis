<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleWorkspaceDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UserOffboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_user_inactive_suspends_their_workspace_account(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('setSuspended')->once()->with('offboard1@crc.pshs.edu.ph', true);
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $employee = User::factory()->create(['email' => 'offboard1@crc.pshs.edu.ph', 'status' => 'active']);

        $this->actingAs($this->userWithPermission())
            ->delete(route('users.destroy', $employee->id))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'status' => 'inactive']);
    }

    public function test_reactivating_a_user_unsuspends_their_workspace_account(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('setSuspended')->once()->with('reactivate1@crc.pshs.edu.ph', false);
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $employee = User::factory()->create(['email' => 'reactivate1@crc.pshs.edu.ph', 'status' => 'inactive']);

        $this->actingAs($this->userWithPermission())
            ->post(route('users.activate', $employee->id))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'status' => 'active']);
    }

    public function test_status_change_via_the_general_edit_form_also_suspends_the_account(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('setSuspended')->once()->with('editform1@crc.pshs.edu.ph', true);
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $employee = User::factory()->create(['email' => 'editform1@crc.pshs.edu.ph', 'status' => 'active', 'name' => 'Edit Form Test']);

        $this->actingAs($this->userWithPermission())
            ->put(route('users.update', $employee->id), [
                'name' => 'Edit Form Test',
                'sex' => 'Male',
                'email' => 'editform1@crc.pshs.edu.ph',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('users.index'));
    }

    public function test_editing_a_user_without_changing_status_does_not_touch_workspace(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldNotReceive('setSuspended');
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $employee = User::factory()->create(['email' => 'nochange1@crc.pshs.edu.ph', 'status' => 'active', 'name' => 'No Change Test']);

        $this->actingAs($this->userWithPermission())
            ->put(route('users.update', $employee->id), [
                'name' => 'No Change Test Updated',
                'sex' => 'Male',
                'email' => 'nochange1@crc.pshs.edu.ph',
                'status' => 'active',
            ])
            ->assertRedirect(route('users.index'));
    }

    public function test_suspension_is_skipped_when_the_user_has_no_official_domain_email(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldNotReceive('setSuspended');
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $employee = User::factory()->create(['email' => 'personal@gmail.com', 'status' => 'active']);

        $this->actingAs($this->userWithPermission())
            ->delete(route('users.destroy', $employee->id))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'status' => 'inactive']);
    }

    private function userWithPermission(): User
    {
        $role = Role::create(['name' => 'User Offboarding Test '.uniqid()]);

        foreach (['users.view', 'hr.employees.manage'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Users', 'description' => $name]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
