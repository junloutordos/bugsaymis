<?php

namespace Tests\Feature\Sos;

use App\Events\Sos\SosAlertTriggered;
use App\Jobs\Sos\NotifySosEmergencyContact;
use App\Jobs\Sos\NotifySosResponders;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SosAlertServiceTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_trigger_creates_alert_broadcasts_and_dispatches_jobs_when_geofencing_unconfigured(): void
    {
        // No OnlinePunchGeofenceZone rows => CampusPresenceService resolves 'unconfigured',
        // which this feature deliberately allows through rather than blocking everyone.
        Event::fake([SosAlertTriggered::class]);
        Queue::fake();

        SosEscalationTier::create([
            'alert_type' => 'medical', 'order' => 1, 'timeout_minutes' => 10,
            'channels' => ['in_app'], 'notify_external' => false,
        ]);

        $user = User::factory()->create();
        $service = app(SosAlertService::class);

        $result = $service->trigger(
            triggerable: $user,
            alertType: 'medical',
            isSilent: false,
            lat: null,
            lng: null,
            accuracy: null,
            ip: '127.0.0.1',
        );

        $this->assertFalse($result['blocked']);
        $this->assertNotNull($result['alert']);
        $this->assertSame('triggered', $result['alert']->status);
        $this->assertDatabaseHas('sos_alert_events', ['sos_alert_id' => $result['alert']->id, 'type' => 'triggered']);

        Event::assertDispatched(SosAlertTriggered::class);
        Queue::assertPushed(NotifySosResponders::class);
        Queue::assertPushed(NotifySosEmergencyContact::class);
    }

    public function test_trigger_is_blocked_when_geofence_reports_no_permission(): void
    {
        \App\Models\HR\OnlinePunchGeofenceZone::create([
            'label' => 'Main Campus', 'latitude' => 8.9475, 'longitude' => 125.5406,
            'radius_meters' => 200, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $service = app(SosAlertService::class);

        $result = $service->trigger(
            triggerable: $user, alertType: 'general', isSilent: false,
            lat: null, lng: null, accuracy: null, ip: '127.0.0.1',
        );

        $this->assertTrue($result['blocked']);
        $this->assertSame('no_permission', $result['reason']);
        $this->assertDatabaseCount('sos_alerts', 0);
    }

    public function test_silent_trigger_defaults_alert_type_flag_is_stored(): void
    {
        $user = User::factory()->create();
        $service = app(SosAlertService::class);

        $result = $service->trigger(
            triggerable: $user, alertType: 'security', isSilent: true,
            lat: null, lng: null, accuracy: null, ip: '127.0.0.1',
        );

        $this->assertTrue($result['alert']->is_silent);
    }

    public function test_trigger_persists_a_resolved_location_snapshot(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $user = User::factory()->create(['office_id' => null]);

        $result = app(SosAlertService::class)->trigger(
            triggerable: $user, alertType: 'general', isSilent: false,
            lat: null, lng: null, accuracy: null, ip: null,
        );

        $this->assertSame('unknown', $result['alert']->resolved_location_type);
        $this->assertSame('fallback', $result['alert']->resolved_source);
        $this->assertNotNull($result['alert']->resolved_location_label);
    }
}
