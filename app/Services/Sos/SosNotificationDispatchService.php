<?php

namespace App\Services\Sos;

use App\Mail\SosAlertMail;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\Sos\SosNotificationLog;
use App\Models\User;
use App\Services\StudentAttendance\FcmService;
use App\Services\StudentAttendance\SmsGateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SosNotificationDispatchService
{
    public function __construct(
        private readonly SmsGateService $sms,
        private readonly FcmService $fcm,
    ) {}

    public function notifyEmergencyContact(SosAlert $alert): void
    {
        $triggerable = $alert->triggerable;

        if ($triggerable instanceof User) {
            $profile = $triggerable->employeeProfile;
            if ($profile && $profile->emergency_contact_phone) {
                $sent = $this->sms->send(
                    $profile->emergency_contact_phone,
                    "PSHS-CRC SOS Alert: {$triggerable->name} triggered an emergency alert on campus. Please contact the school immediately.",
                );
                $this->log($alert, 'sms', 'user_emergency_contact', null, $profile->emergency_contact_name, $sent);
            }
            return;
        }

        if ($triggerable instanceof \App\Models\Student) {
            // Student has no `name` attribute — the legacy table only has
            // firstname/lastname (see app/Models/Student.php, read-only/guarded).
            $studentName = trim("{$triggerable->firstname} {$triggerable->lastname}") ?: 'Your child';

            foreach ($triggerable->parentContacts as $parent) {
                if ($parent->wantsPushNotification()) {
                    $sent = $this->fcm->send(
                        $parent->fcm_device_token,
                        'Emergency SOS Alert',
                        "{$studentName} triggered an SOS alert on campus.",
                        ['type' => 'sos_alert', 'alert_id' => (string) $alert->id],
                    );
                    $this->log($alert, 'push', 'parent_contact', $parent->id, $parent->name, $sent);
                }

                if ($parent->notify_sms && ! empty($parent->mobile_phone)) {
                    $sent = $this->sms->send(
                        $parent->mobile_phone,
                        "PSHS-CRC SOS Alert: {$studentName} triggered an emergency alert on campus.",
                    );
                    $this->log($alert, 'sms', 'parent_contact', $parent->id, $parent->name, $sent);
                }
            }
        }
    }

    public function notifyTier(SosAlert $alert, SosEscalationTier $tier): void
    {
        foreach ($this->resolveTierResponders($tier) as $user) {
            if (in_array('sms', $tier->channels, true)) {
                $mobile = $user->employeeProfile?->mobile_number;
                if ($mobile) {
                    $sent = $this->sms->send($mobile, $this->smsMessageFor($alert));
                    $this->log($alert, 'sms', 'user', $user->id, $user->name, $sent);
                }
            }

            if (in_array('email', $tier->channels, true) && $user->email) {
                $sent = true;
                try {
                    Mail::to($user->email)->send(new SosAlertMail($alert, $user));
                } catch (Throwable $e) {
                    Log::warning('Sos: failed to email responder.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                    $sent = false;
                }
                $this->log($alert, 'email', 'user', $user->id, $user->name, $sent);
            }

            if (in_array('in_app', $tier->channels, true)) {
                // Real delivery is the SosAlertTriggered/SosAlertUpdated broadcast
                // itself — logged here for audit completeness only.
                $this->log($alert, 'in_app', 'user', $user->id, $user->name, true);
            }
        }

        if ($tier->notify_external) {
            $this->notifyExternalContacts($alert);
        }
    }

    private function resolveTierResponders(SosEscalationTier $tier): Collection
    {
        $fromRole = $tier->role_id
            ? User::whereHas('roles', fn ($q) => $q->where('roles.id', $tier->role_id))->get()
            : collect();

        return $fromRole->merge($tier->users)->unique('id');
    }

    private function notifyExternalContacts(SosAlert $alert): void
    {
        $contacts = SosExternalContact::where('active', true)->get()
            ->filter(fn (SosExternalContact $c) => in_array($alert->alert_type, $c->alert_types ?? [], true));

        foreach ($contacts as $contact) {
            if (in_array($contact->channel, ['sms', 'both'], true) && $contact->phone) {
                $sent = $this->sms->send($contact->phone, $this->smsMessageFor($alert));
                $this->log($alert, 'sms', 'external_contact', $contact->id, $contact->name, $sent);
            }

            if (in_array($contact->channel, ['email', 'both'], true) && $contact->email) {
                $sent = true;
                try {
                    Mail::to($contact->email)->send(new SosAlertMail($alert, null));
                } catch (Throwable $e) {
                    Log::warning('Sos: failed to email external contact.', ['contact_id' => $contact->id, 'error' => $e->getMessage()]);
                    $sent = false;
                }
                $this->log($alert, 'email', 'external_contact', $contact->id, $contact->name, $sent);
            }
        }
    }

    private function smsMessageFor(SosAlert $alert): string
    {
        $label = ucfirst(str_replace('_', ' ', $alert->alert_type));
        return "PSHS-CRC SOS Alert #{$alert->id} ({$label}) triggered on campus. Respond via the Atlas SOS Command Center.";
    }

    private function log(SosAlert $alert, string $channel, string $recipientType, ?int $recipientId, ?string $label, bool $sent): void
    {
        SosNotificationLog::create([
            'sos_alert_id'    => $alert->id,
            'channel'         => $channel,
            'recipient_type'  => $recipientType,
            'recipient_id'    => $recipientId,
            'recipient_label' => $label,
            'sent'            => $sent,
            'sent_at'         => $sent ? now() : null,
        ]);
    }
}
