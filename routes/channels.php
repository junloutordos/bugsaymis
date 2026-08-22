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

Broadcast::channel('attendance', function ($user) {
    return $user->isSuperAdmin() || $user->hasRole('Security Guard');
});

Broadcast::channel('biometric-feed', function ($user) {
    return $user->isSuperAdmin() || $user->hasAnyPermission(['hr.biometric.monitor', 'hr.biometric.manage']);
});

Broadcast::channel('sos-responders', function ($user) {
    return $user->isSuperAdmin() || $user->hasPermission('sos.respond');
});

// Any authenticated Atlas web user should see an active campus-wide
// emergency broadcast in real time — unlike sos-responders (triage-only
// staff), this channel is intentionally open to every logged-in employee.
// Not a broadcast-scope bypass: only a `User` (Atlas web session) can ever
// reach this closure — Students/Parents are Sanctum API-only in this app
// (see student_credentials/parent_contacts migrations) and never establish
// a web session capable of hitting the broadcasting auth route at all, so
// this is equivalent to "any authenticated employee", not "any principal".
Broadcast::channel('emergency-alerts', function (\App\Models\User $user) {
    return true;
});
