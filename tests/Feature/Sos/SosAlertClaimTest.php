<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertClaimTest extends TestCase
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

    private function alert(): SosAlert
    {
        return SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);
    }

    public function test_responder_can_claim_and_appears_in_responders_list(): void
    {
        $responder = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")
            ->assertOk()
            ->assertJsonPath('responders.0.user_id', $responder->id);

        $this->assertDatabaseHas('sos_alert_responders', ['sos_alert_id' => $alert->id, 'user_id' => $responder->id, 'unclaimed_at' => null]);
    }

    public function test_claim_is_idempotent(): void
    {
        $responder = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();
        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();

        $this->assertSame(1, \App\Models\Sos\SosAlertResponder::where('sos_alert_id', $alert->id)->whereNull('unclaimed_at')->count());
    }

    public function test_responder_can_unclaim(): void
    {
        $responder = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();
        $this->actingAs($responder)->postJson("/sos/{$alert->id}/unclaim")
            ->assertOk()->assertJsonPath('responders', []);
    }

    public function test_unclaim_by_a_non_claimant_is_a_no_op(): void
    {
        $responder = $this->responder();
        $other = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();
        $this->actingAs($other)->postJson("/sos/{$alert->id}/unclaim")
            ->assertOk()->assertJsonPath('responders.0.user_id', $responder->id);
    }

    public function test_claim_requires_sos_respond_permission(): void
    {
        $user = User::factory()->create();
        $alert = $this->alert();

        $this->actingAs($user)->postJson("/sos/{$alert->id}/claim")->assertForbidden();
    }
}
