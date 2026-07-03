<?php

namespace App\Events\Quiz;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payload never includes is_correct on options — the host/service is the
 * only source of truth for correctness, checked server-side on submit.
 */
class QuizQuestionStarted implements ShouldBroadcastNow
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
        return 'question.started';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
