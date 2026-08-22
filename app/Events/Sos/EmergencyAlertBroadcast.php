<?php

namespace App\Events\Sos;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlertBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('emergency-alerts')];
    }

    public function broadcastAs(): string
    {
        return 'emergency.alert.broadcast';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
