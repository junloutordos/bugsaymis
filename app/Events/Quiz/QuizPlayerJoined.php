<?php

namespace App\Events\Quiz;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts on the public per-session channel so the host's lobby screen
 * updates live as players join. Never includes player_token — that stays
 * private to the joining device.
 */
class QuizPlayerJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $gamePin,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("quiz-session.{$this->gamePin}")];
    }

    public function broadcastAs(): string
    {
        return 'player.joined';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
