<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed /api/chat and protected by the standard web
| session authentication. Responses are JSON — no Inertia rendering.
|
| Rate limiting:
|   - sendMessage is throttled to 60 msg/min per user to prevent spam.
|   - All other endpoints use the default 120 req/min web throttle.
|
*/

// Inertia page — the messenger UI
Route::get('/chat', [ChatController::class, 'index'])
    ->middleware(['web', 'auth'])
    ->name('chat.index');

Route::prefix('api/chat')
    ->middleware(['web', 'auth'])
    ->name('chat.')
    ->group(function () {

        /*
        |----------------------------------------------------------------------
        | Users — searchable list to start new conversations
        |----------------------------------------------------------------------
        */
        Route::get('users', [ChatController::class, 'listUsers'])
            ->name('users');

        /*
        |----------------------------------------------------------------------
        | Conversations
        |----------------------------------------------------------------------
        */

        // Total unread message count across all conversations (sidebar badge)
        Route::get('unread-count', [ChatController::class, 'unreadCount'])
            ->name('unread-count');

        // List all conversations for the auth user (sidebar)
        Route::get('conversations', [ChatController::class, 'getConversations'])
            ->name('conversations.index');

        // Start a new direct or group conversation
        Route::post('conversations', [ChatController::class, 'startConversation'])
            ->name('conversations.start');

        /*
        |----------------------------------------------------------------------
        | Messages (scoped to a conversation)
        |----------------------------------------------------------------------
        */

        // Paginated message history for one conversation
        Route::get('conversations/{conversation}/messages', [ChatController::class, 'getMessages'])
            ->name('conversations.messages');

        // Send a message (text or with attachment) — throttled
        Route::post('conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])
            ->middleware('throttle:60,1')
            ->name('conversations.send');

        // Mark all messages in a conversation as read
        Route::post('conversations/{conversation}/read', [ChatController::class, 'markAsRead'])
            ->name('conversations.read');
    });
