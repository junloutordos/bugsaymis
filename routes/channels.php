<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| conversation.{id}  — participants only (not left)
| user.{id}          — the user themselves only (personal notification channel)
|
*/

// Personal channel — used for cross-page new-message badges & browser notifications
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    return $conversation->participants()
        ->where('users.id', $user->id)
        ->whereNull('conversation_user.left_at')
        ->exists();
});
