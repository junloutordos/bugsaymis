<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast immediately (ShouldBroadcastNow) so messages appear in real-time
 * without waiting for a queue worker.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly array $message,   // pre-serialized payload from ChatController
    ) {}

    /**
     * Broadcast on a private channel per conversation.
     * All participants in the conversation listen on this channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversation->id}"),
        ];
    }

    /**
     * Explicit event name — used by the frontend Echo listener.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Only the message payload is sent over the wire — not the Eloquent model.
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
