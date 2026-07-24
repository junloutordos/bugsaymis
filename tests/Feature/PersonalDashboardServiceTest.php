<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PersonalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_online_punch_is_true_for_a_user_with_the_permission(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'hr.online-punch.record'],
            ['module' => 'HR', 'description' => 'Record online time punch'],
        );
        $role = Role::create(['name' => 'OnlinePunchUser_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $payload = app(PersonalDashboardService::class)->payload($user->fresh());

        $this->assertTrue($payload['canOnlinePunch']);
    }

    public function test_can_online_punch_is_false_for_a_user_without_the_permission(): void
    {
        $user = User::factory()->create();

        $payload = app(PersonalDashboardService::class)->payload($user);

        $this->assertFalse($payload['canOnlinePunch']);
    }
}
