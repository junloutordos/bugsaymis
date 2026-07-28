<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleWorkspaceDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_employee_without_an_email_auto_provisions_a_workspace_account(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('emailExists')->andReturn(false);
        $mock->shouldReceive('createUser')
            ->withArgs(fn ($email, $given, $family, $password, $orgUnit) => $orgUnit === '/Staff')
            ->andReturnUsing(fn ($email) => $email);
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $response = $this->actingAs($this->userWithPermission())
            ->post(route('users.store'), [
                'name' => 'Juan Provtest'.uniqid(),
                'sex' => 'Male',
                'email' => '', // Employees form omits this field — posts empty string
                'emp_category' => 'Plantilla Non-Teaching',
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success', fn ($message) => str_contains($message, 'Google account') && str_contains($message, 'temporary password'));

        $this->assertDatabaseHas('users', [
            'account_type' => 'employee',
        ]);
        $created = User::where('account_type', 'employee')->latest('id')->first();
        $this->assertStringEndsWith('@crc.pshs.edu.ph', $created->email);
    }

    public function test_creating_a_faculty_member_provisions_under_the_faculty_org_unit(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('emailExists')->andReturn(false);
        $mock->shouldReceive('createUser')
            ->withArgs(fn ($email, $given, $family, $password, $orgUnit) => $orgUnit === '/Faculty')
            ->andReturnUsing(fn ($email) => $email);
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $this->actingAs($this->userWithPermission())
            ->post(route('users.store'), [
                'name' => 'Maria Facultytest'.uniqid(),
                'sex' => 'Female',
                'email' => '',
                'emp_category' => 'Plantilla Teaching',
            ])
            ->assertRedirect(route('users.index'));
    }

    public function test_creating_a_user_with_an_explicit_email_does_not_trigger_provisioning(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldNotReceive('createUser');
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $email = 'explicit'.uniqid().'@crc.pshs.edu.ph';

        $this->actingAs($this->userWithPermission())
            ->post(route('users.store'), [
                'name' => 'Explicit Email User',
                'sex' => 'Male',
                'email' => $email,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    public function test_employee_creation_still_succeeds_when_workspace_provisioning_fails(): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('emailExists')->andReturn(false);
        $mock->shouldReceive('createUser')->andThrow(new \RuntimeException('Workspace API down'));
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $response = $this->actingAs($this->userWithPermission())
            ->post(route('users.store'), [
                'name' => 'Juan Failtest'.uniqid(),
                'sex' => 'Male',
                'email' => '',
                'emp_category' => 'Plantilla Non-Teaching',
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success', fn ($message) => str_contains($message, 'could not be auto-created'));

        $this->assertDatabaseHas('users', ['account_type' => 'employee']);
    }

    private function userWithPermission(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'users.view'],
            ['module' => 'Users', 'description' => 'users.view'],
        );
        $role = Role::create(['name' => 'User Provisioning Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
