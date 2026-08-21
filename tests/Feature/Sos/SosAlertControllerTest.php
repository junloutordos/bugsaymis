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
}
