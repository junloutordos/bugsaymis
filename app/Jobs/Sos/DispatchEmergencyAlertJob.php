<?php

namespace App\Jobs\Sos;

use App\Mail\EmergencyAlertMail;
use App\Models\Sos\EmergencyAlert;
use App\Services\NoticeAudienceResolver;
use App\Services\StudentAttendance\FcmService;
use App\Services\StudentAttendance\SmsGateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class DispatchEmergencyAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    // Single attempt — a retry would double-notify every recipient of a
    // life-safety broadcast.
    public int $tries = 1;

    public function __construct(public int $emergencyAlertId)
    {
        // Never share a queue with 'bulk' — a large announcement fan-out
        // must never delay an emergency broadcast's SMS/push/email delivery.
        $this->onQueue('emergency');
    }

    public function handle(NoticeAudienceResolver $resolver, FcmService $fcm): void
    {
        $alert = EmergencyAlert::find($this->emergencyAlertId);

        if (! $alert) {
            logger()->error('DispatchEmergencyAlertJob: alert missing', ['emergency_alert_id' => $this->emergencyAlertId]);
            return;
        }

        $resolved = $resolver->resolve($alert->audience);
        // title/message are duplicated into the data payload (not just the
        // FCM "notification" fields) because the app's foreground handler
        // reads message.data exclusively, to show the takeover without a
        // second network round-trip.
        $pushData = [
            'type' => 'emergency_alert',
            'emergency_alert_id' => (string) $alert->id,
            'title' => $alert->title,
            'message' => $alert->message,
        ];
        $smsGate = app(SmsGateService::class);

        foreach ($resolved['users'] as $user) {
            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new EmergencyAlertMail($alert, $user));
                } catch (\Throwable $e) {
                    logger()->warning('DispatchEmergencyAlertJob: employee email failed', [
                        'emergency_alert_id' => $alert->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        foreach ($resolved['students'] as $student) {
            $token = $student->credential?->fcm_device_token;
            if ($token) {
                $fcm->send($token, $alert->title, $alert->message, $pushData);
            }
        }

        foreach ($resolved['parents'] as $parent) {
            if ($parent->wantsPushNotification()) {
                $fcm->send($parent->fcm_device_token, $alert->title, $alert->message, $pushData);
            }
            if ($parent->notify_sms && ! empty($parent->mobile_phone)) {
                $smsGate->send($parent->mobile_phone, "PSHS-CRC ALERT: {$alert->title} — {$alert->message}");
            }
        }

        logger()->info('DispatchEmergencyAlertJob: complete', [
            'emergency_alert_id' => $alert->id,
            'audience'           => $alert->audience,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('DispatchEmergencyAlertJob: job FAILED', [
            'emergency_alert_id' => $this->emergencyAlertId,
            'error'              => $e->getMessage(),
        ]);
    }
}
