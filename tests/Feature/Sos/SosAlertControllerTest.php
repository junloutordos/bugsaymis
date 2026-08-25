<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertControllerTest extends TestCase
{
    use RefreshDatabase;

    private function responder(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_any_authenticated_user_can_trigger(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/sos/trigger', [
            'alert_type' => 'general', 'is_silent' => false,
        ]);

        $response->assertCreated()->assertJson(['blocked' => false]);
        $this->assertDatabaseHas('sos_alerts', ['triggerable_id' => $user->id, 'triggerable_type' => User::class]);
    }

    public function test_trigger_without_permission_still_works_since_sos_trigger_is_not_gated(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/sos/trigger', ['alert_type' => 'medical']);
        $response->assertStatus(201);
    }

    public function test_command_center_requires_sos_respond_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/sos')->assertForbidden();

        $responder = $this->responder();
        $this->actingAs($responder)->get('/sos')->assertOk();
    }

    public function test_acknowledge_and_resolve_flow(): void
    {
        $responder = $this->responder();
        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/acknowledge")
            ->assertOk()->assertJsonPath('status', 'acknowledged');

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/resolve", ['notes' => 'Handled'])
            ->assertOk()->assertJsonPath('status', 'resolved');
    }

    public function test_index_includes_resolved_and_current_location(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $reporter = User::factory()->create(['office_id' => null]);
        $this->actingAs($reporter)->postJson('/sos/trigger', ['alert_type' => 'general']);

        $responder = $this->responder();
        $response = $this->actingAs($responder)->getJson('/sos/'.SosAlert::first()->id);

        $response->assertOk()
            ->assertJsonPath('resolved_location.type', 'unknown')
            ->assertJsonStructure(['current_location' => ['type', 'label', 'building', 'room', 'source']])
            ->assertJsonStructure(['gps_badge' => ['on_campus', 'zone_label']])
            ->assertJsonPath('responders', []);
    }

    public function test_closed_alert_has_no_live_current_location(): void
    {
        $responder = $this->responder();
        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1,
            'triggered_at' => now(), 'resolved_at' => now(),
        ]);

        $this->actingAs($responder)->getJson("/sos/{$alert->id}")
            ->assertOk()->assertJsonPath('current_location', null);
    }

    public function test_active_status_reports_open_alert_count(): void
    {
        $responder = $this->responder();
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id, 'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now()]);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id, 'alert_type' => 'general', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);

        $this->actingAs($responder)->getJson('/sos/active-status')
            ->assertOk()->assertJsonPath('active', true)->assertJsonPath('count', 1);
    }

    public function test_active_status_requires_sos_respond_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/sos/active-status')->assertForbidden();
    }
}
