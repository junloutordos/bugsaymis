<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\RequestStatusNotification;
use Illuminate\Support\Facades\Broadcast;

class NotificationService
{
    /**
     * Send a request-status notification to a user.
     * 1. Persists to DB (notifications table)
     * 2. Broadcasts real-time via Soketi (in-app bell)
     * 3. Sends Web Push to all registered browser subscriptions
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
            // 1 — DB + in-app bell
            $user->notify(new RequestStatusNotification(
                requestType: $requestType,
                referenceNo: $referenceNo,
                newStatus:   $newStatus,
                url:         $url,
                remarks:     $remarks,
            ));

            // 2 — Real-time Soketi broadcast on user.{id} channel
            event(new \Illuminate\Broadcasting\BroadcastEvent(
                new class($user->id, [
                    'request_type' => $requestType,
                    'reference_no' => $referenceNo,
                    'status'       => $newStatus,
                    'url'          => $url,
                    'remarks'      => $remarks,
                    'created_at'   => now()->toIso8601String(),
                ]) implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow {
                    public function __construct(private int $userId, private array $payload) {}
                    public function broadcastOn() { return [new \Illuminate\Broadcasting\PrivateChannel("user.{$this->userId}")]; }
                    public function broadcastWith() { return $this->payload; }
                    public function broadcastAs() { return 'request.status.updated'; }
                }
            ));
        } catch (\Throwable $e) {
            logger()->error('Failed to send in-app notification', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        // 3 — Web Push (fire-and-forget, never blocks the request)
        try {
            static::sendWebPush($user, $requestType, $referenceNo, $newStatus, $url, $remarks);
        } catch (\Throwable $e) {
            logger()->error('Failed to send web push', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private static function sendWebPush(
        User $user, string $requestType, string $referenceNo,
        string $newStatus, string $url, ?string $remarks,
    ): void {
        if (! class_exists(\Minishlink\WebPush\WebPush::class)) return;

        $vapidPublic  = env('VAPID_PUBLIC_KEY');
        $vapidPrivate = env('VAPID_PRIVATE_KEY');
        $vapidSubject = env('VAPID_SUBJECT', 'mailto:portal@crc.pshs.edu.ph');

        if (! $vapidPublic || ! $vapidPrivate) return;

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) return;

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => $vapidSubject,
                'publicKey'  => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ]);

        $payload = json_encode([
            'title' => "{$requestType} — {$newStatus}",
            'body'  => $referenceNo . ($remarks ? ": {$remarks}" : ''),
            'icon'  => '/images/pshslogo.png',
            'data'  => ['url' => $url],
            'tag'   => 'crcmis-' . $user->id . '-' . time(),
        ]);

        foreach ($subscriptions as $sub) {
            try {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?? 'aesgcm',
                ]);
                $webPush->queueNotification($subscription, $payload);
            } catch (\Throwable $e) {
                logger()->warning('Invalid push subscription', ['id' => $sub->id, 'error' => $e->getMessage()]);
                $sub->delete(); // Remove invalid subscription
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                logger()->info('Push notification sent', ['endpoint' => substr($report->getEndpoint(), 0, 60)]);
            } else {
                logger()->warning('Push notification failed', [
                    'endpoint' => substr($report->getEndpoint(), 0, 60),
                    'reason'   => $report->getReason(),
                    'expired'  => $report->isSubscriptionExpired(),
                ]);
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
            }
        }
    }
}
