<?php

namespace Tests\Feature\OPCR;

use App\Models\OPCR\OpcrSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_update_the_signatories(): void
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->put(route('opcr-settings.update'), [
            'campus_director_name' => 'RAMIL A. SANCHEZ',
            'executive_director_name' => 'RONNALEE N. ORTEZA',
            'commitment_statement' => 'Custom commitment text.',
        ]);

        $response->assertRedirect();
        $setting = OpcrSetting::current();
        $this->assertEquals('RAMIL A. SANCHEZ', $setting->campus_director_name);
        $this->assertEquals('RONNALEE N. ORTEZA', $setting->executive_director_name);
        $this->assertEquals('Custom commitment text.', $setting->commitment_statement);
    }

    public function test_oic_campus_director_name_is_no_longer_a_settable_field(): void
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->put(route('opcr-settings.update'), [
            'campus_director_name' => 'RAMIL A. SANCHEZ',
            'oic_campus_director_name' => 'MICHELLE B. FERNANDO',
        ]);

        $response->assertRedirect();
        $this->assertNull(OpcrSetting::current()->oic_campus_director_name);
    }

    public function test_view_only_user_cannot_update_settings(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->put(route('opcr-settings.update'), [
            'campus_director_name' => 'Someone Else',
        ]);

        $response->assertForbidden();
    }
}
