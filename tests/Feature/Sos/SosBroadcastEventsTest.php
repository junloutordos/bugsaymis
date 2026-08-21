<?php

namespace Tests\Feature\Sos;

use App\Events\Sos\SosAlertTriggered;
use App\Events\Sos\SosAlertUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class SosBroadcastEventsTest extends TestCase
{
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
}
