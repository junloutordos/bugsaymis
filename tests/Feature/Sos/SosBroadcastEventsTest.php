<?php

namespace Tests\Feature\Sos;

use App\Events\Sos\SosAlertTriggered;
use App\Events\Sos\SosAlertUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosBroadcastEventsTest extends TestCase
{
    // Only the new DB-touching test below needs this — the two plain-object
    // tests above never hit the database, so RefreshDatabase is a no-op for
    // them and only exists to keep the new test isolated from suite state.
    use RefreshDatabase;

    public function test_sos_alert_triggered_broadcasts_correctly(): void
    {
        $event = new SosAlertTriggered(['id' => 1, 'status' => 'triggered']);

        $channels = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-sos-responders', $channels[0]->name);
        $this->assertSame('sos.alert.triggered', $event->broadcastAs());
        $this->assertSame(['id' => 1, 'status' => 'triggered'], $event->broadcastWith());
    }

    public function test_sos_alert_updated_broadcasts_correctly(): void
    {
        $event = new SosAlertUpdated(['id' => 1, 'status' => 'resolved']);

        $this->assertSame('sos.alert.updated', $event->broadcastAs());
        $this->assertSame(['id' => 1, 'status' => 'resolved'], $event->broadcastWith());
    }

    public function test_trigger_broadcast_includes_reporter_name_and_location(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\Sos\SosAlertTriggered::class]);
        \App\Models\Sos\SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $user = \App\Models\User::factory()->create(['name' => 'Juan Dela Cruz', 'office_id' => null]);

        app(\App\Services\Sos\SosAlertService::class)->trigger(
            triggerable: $user, alertType: 'general', isSilent: false,
            lat: null, lng: null, accuracy: null, ip: null,
        );

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\Sos\SosAlertTriggered::class,
            fn (\App\Events\Sos\SosAlertTriggered $event) =>
                $event->payload['reporter_name'] === 'Juan Dela Cruz'
                && $event->payload['resolved_location_type'] === 'unknown'
        );
    }
}
