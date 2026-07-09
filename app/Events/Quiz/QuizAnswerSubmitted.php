<?php

namespace App\Events\Quiz;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Counts only, no identities and no correctness — this goes out on the public
 * session channel while the question is still live, so it must never leak
 * anything a player could use before the reveal.
 */
class QuizAnswerSubmitted implements ShouldBroadcastNow
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
        return 'answer.submitted';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
