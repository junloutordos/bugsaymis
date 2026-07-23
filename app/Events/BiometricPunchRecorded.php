<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired immediately after a biometric punch is ingested (file import or
 * live Atlas Sentinel bridge). Broadcasts on the private 'biometric-feed'
 * channel so the HR/Administrator/OCD live monitor updates in real time.
 */
class BiometricPunchRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $payload,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('biometric-feed'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'biometric.punch.recorded';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
