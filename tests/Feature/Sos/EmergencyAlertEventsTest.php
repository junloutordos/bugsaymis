<?php

namespace Tests\Feature\Sos;

use App\Events\Sos\EmergencyAlertBroadcast;
use App\Events\Sos\EmergencyAlertResolved;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class EmergencyAlertEventsTest extends TestCase
{
    public function test_broadcast_event_shape(): void
    {
        $event = new EmergencyAlertBroadcast(['id' => 1, 'title' => 'Test']);

        $this->assertSame('emergency.alert.broadcast', $event->broadcastAs());
        $this->assertEquals([new PrivateChannel('emergency-alerts')], $event->broadcastOn());
        $this->assertSame(['id' => 1, 'title' => 'Test'], $event->broadcastWith());
    }

    public function test_resolved_event_shape(): void
    {
        $event = new EmergencyAlertResolved(['id' => 1, 'status' => 'resolved']);

        $this->assertSame('emergency.alert.resolved', $event->broadcastAs());
        $this->assertEquals([new PrivateChannel('emergency-alerts')], $event->broadcastOn());
    }
}
