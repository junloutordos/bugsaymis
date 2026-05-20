<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\RequestStatusNotification;
use Illuminate\Support\Facades\Broadcast;

class NotificationService
{
    /**
     * Send a request-status notification to a user.
     * Stores in DB + broadcasts on their private channel via Soketi.
     */
    public static function notifyUser(
        User    $user,
        string  $requestType,
        string  $referenceNo,
        string  $newStatus,
        string  $url,
        ?string $remarks = null,
    ): void {
        try {
            $notification = new RequestStatusNotification(
                requestType: $requestType,
                referenceNo: $referenceNo,
                newStatus:   $newStatus,
                url:         $url,
                remarks:     $remarks,
            );

            // Persist to DB (creates the notifications row)
            $user->notify($notification);

            // Broadcast real-time update on the user's private channel
            Broadcast::private("user.{$user->id}")
                ->event('request.status.updated', [
                    'request_type' => $requestType,
                    'reference_no' => $referenceNo,
                    'status'       => $newStatus,
                    'url'          => $url,
                    'remarks'      => $remarks,
                    'created_at'   => now()->toIso8601String(),
                ]);
        } catch (\Throwable $e) {
            logger()->error('Failed to send request notification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
