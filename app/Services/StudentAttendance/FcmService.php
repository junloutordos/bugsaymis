<?php

namespace App\Services\StudentAttendance;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FcmService
{
    private mixed $messaging = null;
    private bool  $ready     = false;

    public function __construct()
    {
        $credentialsPath = config('services.firebase.credentials');

        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            Log::debug('FCM: credentials not configured — push notifications disabled.');
            return;
        }

        try {
            $this->messaging = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->createMessaging();
            $this->ready = true;
        } catch (Throwable $e) {
            Log::warning('FCM: failed to initialize messaging client.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send a push notification to a single FCM device token.
     * Returns true on success, false on any failure.
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (! $this->ready) return false;

        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            return true;
        } catch (Throwable $e) {
            Log::warning('FCM: failed to send push notification.', [
                'token_prefix' => substr($token, 0, 20) . '…',
                'error'        => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function isReady(): bool
    {
        return $this->ready;
    }
}
