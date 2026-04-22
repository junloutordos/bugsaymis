<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to an individual user's private channel whenever a message
 * is sent to one of their conversations.
 *
 * This is separate from MessageSent (which targets conversation.{id})
 * so that users receive badge/browser notifications even when they are
 * not on the Chat page and therefore not subscribed to conversation channels.
 */
class NewMessageNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $recipientId,
        public readonly array $preview,   // { conversation_id, message }
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->recipientId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.message';
    }

    public function broadcastWith(): array
    {
        return $this->preview;
    }
}
