<?php

namespace Tests\Feature\Sos;

use App\Models\HR\EmployeeProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.manage'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/sos/settings')->assertForbidden();
    }

    public function test_admin_can_create_tier_and_external_contact(): void
    {
        $admin = $this->admin();
        $responder = User::factory()->create();

        $this->actingAs($admin)->post('/sos/settings/tiers', [
            'alert_type' => 'fire_disaster', 'order' => 1, 'timeout_minutes' => 15,
            'channels' => ['in_app', 'sms'], 'notify_external' => true, 'user_ids' => [$responder->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('sos_escalation_tiers', ['alert_type' => 'fire_disaster', 'order' => 1]);
        $this->assertDatabaseHas('sos_escalation_tier_users', ['user_id' => $responder->id]);

        $this->actingAs($admin)->post('/sos/settings/external-contacts', [
            'name' => 'Butuan BFP', 'phone' => '09170000001', 'alert_types' => ['fire_disaster'], 'channel' => 'sms', 'active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('sos_external_contacts', ['name' => 'Butuan BFP']);
    }

    public function test_admin_can_set_responder_mobile_number(): void
    {
        $admin = $this->admin();
        $responder = User::factory()->create();
        EmployeeProfile::create(['user_id' => $responder->id]);

        $this->actingAs($admin)->post("/sos/settings/responders/{$responder->id}/mobile", [
            'mobile_number' => '09171234567',
        ])->assertRedirect();

        $this->assertSame('09171234567', $responder->fresh()->employeeProfile->mobile_number);
    }
}
