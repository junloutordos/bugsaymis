<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosSelfServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function openAlertFor(User $reporter): SosAlert
    {
        return SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $reporter->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);
    }

    public function test_reporter_can_poll_their_own_alert_status(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $reporter = User::factory()->create(['office_id' => null]);
        $this->actingAs($reporter)->postJson('/sos/trigger', ['alert_type' => 'general']);

        $alert = SosAlert::first();
        $this->actingAs($reporter)->getJson("/sos/{$alert->id}/mine")
            ->assertOk()
            ->assertJsonPath('status', 'triggered')
            ->assertJsonStructure(['resolved_location_type', 'resolved_location_label', 'events']);
    }

    public function test_a_different_user_cannot_poll_someone_elses_alert(): void
    {
        $reporter = User::factory()->create();
        $other = User::factory()->create();
        $alert = $this->openAlertFor($reporter);

        $this->actingAs($other)->getJson("/sos/{$alert->id}/mine")->assertForbidden();
    }

    public function test_reporter_can_mark_themselves_safe(): void
    {
        $reporter = User::factory()->create();
        $alert = $this->openAlertFor($reporter);

        $this->actingAs($reporter)->postJson("/sos/{$alert->id}/mine/end")
            ->assertOk()->assertJsonPath('status', 'resolved');
    }

    public function test_a_different_user_cannot_end_someone_elses_alert(): void
    {
        $reporter = User::factory()->create();
        $other = User::factory()->create();
        $alert = $this->openAlertFor($reporter);

        $this->actingAs($other)->postJson("/sos/{$alert->id}/mine/end")->assertForbidden();
    }

    public function test_ending_an_already_closed_alert_returns_conflict(): void
    {
        $reporter = User::factory()->create();
        $alert = $this->openAlertFor($reporter);
        $this->actingAs($reporter)->postJson("/sos/{$alert->id}/mine/end")->assertOk();

        $this->actingAs($reporter)->postJson("/sos/{$alert->id}/mine/end")->assertStatus(409);
    }
}
