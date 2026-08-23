<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\EmergencyAlert;
use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmergencyAlertControllerTest extends TestCase
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

    public function test_standalone_create_broadcasts_immediately_and_queues_dispatch(): void
    {
        Event::fake([\App\Events\Sos\EmergencyAlertBroadcast::class]);
        Bus::fake([\App\Jobs\Sos\DispatchEmergencyAlertJob::class]);

        $response = $this->actingAs($this->responder())->postJson(route('sos.broadcast.store'), [
            'title' => 'Weather Advisory', 'message' => 'Classes suspended.',
            'severity' => 'warning', 'audience' => 'all',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('emergency_alerts', [
            'title' => 'Weather Advisory', 'source' => 'manual', 'sos_alert_id' => null, 'status' => 'active',
        ]);
        Event::assertDispatched(\App\Events\Sos\EmergencyAlertBroadcast::class);
        Bus::assertDispatched(\App\Jobs\Sos\DispatchEmergencyAlertJob::class);
    }

    public function test_escalate_from_sos_sets_source_and_link(): void
    {
        Event::fake([\App\Events\Sos\EmergencyAlertBroadcast::class]);
        Bus::fake([\App\Jobs\Sos\DispatchEmergencyAlertJob::class]);

        $sosAlert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'security', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        $response = $this->actingAs($this->responder())
            ->postJson(route('sos.broadcast.from-sos', $sosAlert), [
                'title' => 'Security Incident', 'message' => 'Please remain indoors.',
                'severity' => 'critical', 'audience' => 'all',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('emergency_alerts', [
            'source' => 'escalated', 'sos_alert_id' => $sosAlert->id,
        ]);
    }

    public function test_resolve_marks_status_and_broadcasts_follow_up(): void
    {
        Event::fake([\App\Events\Sos\EmergencyAlertResolved::class]);

        $responder = $this->responder();
        $alert = EmergencyAlert::create([
            'title' => 'Test', 'message' => 'Body', 'severity' => 'warning',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $responder->id,
        ]);

        $response = $this->actingAs($responder)->postJson(route('sos.broadcast.resolve', $alert));

        $response->assertOk();
        $this->assertSame('resolved', $alert->fresh()->status);
        Event::assertDispatched(\App\Events\Sos\EmergencyAlertResolved::class);
    }

    public function test_resolve_rejects_an_already_resolved_alert(): void
    {
        $responder = $this->responder();
        $alert = EmergencyAlert::create([
            'title' => 'Test', 'message' => 'Body', 'severity' => 'warning',
            'audience' => 'all', 'status' => 'resolved', 'source' => 'manual', 'created_by' => $responder->id,
        ]);

        $this->actingAs($responder)->postJson(route('sos.broadcast.resolve', $alert))->assertStatus(422);
    }

    public function test_broadcast_requires_sos_respond_permission(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('sos.broadcast.store'), [
            'title' => 'X', 'message' => 'Y', 'severity' => 'info', 'audience' => 'all',
        ])->assertForbidden();
    }

    public function test_escalate_from_sos_requires_sos_respond_permission(): void
    {
        $sosAlert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'general', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('sos.broadcast.from-sos', $sosAlert), [
                'title' => 'X', 'message' => 'Y', 'severity' => 'info', 'audience' => 'all',
            ])->assertForbidden();
    }

    public function test_emergency_status_reports_active_alert_and_severity_to_any_employee(): void
    {
        EmergencyAlert::create([
            'title' => 'Test', 'message' => 'Body', 'severity' => 'critical',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $this->responder()->id,
        ]);

        // A plain employee with no sos.respond permission must still get 200 —
        // this endpoint is intentionally open, mirroring the emergency-alerts
        // Echo channel's own authorization policy.
        $response = $this->actingAs(User::factory()->create())->getJson(route('sos.emergency-status'));

        $response->assertOk()->assertJson(['active' => true, 'severity' => 'critical']);
    }

    public function test_emergency_status_reports_inactive_when_nothing_is_active(): void
    {
        $response = $this->actingAs(User::factory()->create())->getJson(route('sos.emergency-status'));

        $response->assertOk()->assertJson(['active' => false, 'severity' => null]);
    }
}
