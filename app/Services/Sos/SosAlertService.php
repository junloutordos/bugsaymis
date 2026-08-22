<?php

namespace App\Services\Sos;

use App\Events\Sos\SosAlertTriggered;
use App\Events\Sos\SosAlertUpdated;
use App\Jobs\Sos\NotifySosEmergencyContact;
use App\Jobs\Sos\NotifySosResponders;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosAlertEvent;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use App\Services\CampusPresenceService;
use Illuminate\Database\Eloquent\Model;

class SosAlertService
{
    public function __construct(private readonly CampusPresenceService $campusPresence) {}

    /**
     * @return array{blocked: bool, reason: ?string, alert: ?SosAlert}
     */
    public function trigger(
        Model $triggerable,
        string $alertType,
        bool $isSilent,
        ?float $lat,
        ?float $lng,
        ?float $accuracy,
        ?string $ip,
    ): array {
        $gate = $this->campusPresence->resolveLocationGate($lat, $lng, $accuracy, $ip);

        // A false "we notified someone" is worse than an honest redirect to
        // real emergency services — see spec decision #4. 'coarse' and
        // 'unconfigured' are allowed through deliberately: blocking a real
        // emergency over GPS imprecision or an unset-up geofence is worse
        // than the alternative.
        if (in_array($gate['status'], ['outside', 'no_permission'], true)) {
            return ['blocked' => true, 'reason' => $gate['status'], 'alert' => null];
        }

        $alert = SosAlert::create([
            'triggerable_type'   => get_class($triggerable),
            'triggerable_id'     => $triggerable->getKey(),
            'alert_type'         => $alertType,
            'is_silent'          => $isSilent,
            'status'             => 'triggered',
            'lat'                => $lat,
            'lng'                => $lng,
            'accuracy'           => $accuracy,
            'geofence_zone_id'   => $gate['geofence']['zoneId'] ?? null,
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'triggered',
            'actor_type'   => get_class($triggerable),
            'actor_id'     => $triggerable->getKey(),
            'payload'      => ['alert_type' => $alertType, 'is_silent' => $isSilent],
        ]);

        event(new SosAlertTriggered($this->broadcastPayload($alert)));

        $firstTier = SosEscalationTier::where('alert_type', $alertType)->where('order', 1)->first();
        if ($firstTier) {
            NotifySosResponders::dispatch($alert->id, $firstTier->id);
        }
        NotifySosEmergencyContact::dispatch($alert->id);

        return ['blocked' => false, 'reason' => null, 'alert' => $alert];
    }

    public function acknowledge(SosAlert $alert, User $responder): SosAlertEvent
    {
        $alert->update(['status' => 'acknowledged']);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'acknowledged',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }

    public function verify(SosAlert $alert, User $responder, ?string $note = null): SosAlertEvent
    {
        $alert->update(['status' => 'verified']);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'verified',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => $note ? ['note' => $note] : null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }

    public function markFalseAlarm(SosAlert $alert, User $responder, string $reason): SosAlertEvent
    {
        $alert->update(['status' => 'false_alarm']);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'false_alarm',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => ['reason' => $reason],
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        $count = SosAlert::falseAlarmCount(
            $alert->triggerable_type,
            $alert->triggerable_id,
            (int) config('sos.false_alarm_window_days'),
        );

        if ($count >= (int) config('sos.false_alarm_threshold')) {
            $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->get();
            foreach ($admins as $admin) {
                \App\Services\NotificationService::notifyUser(
                    user: $admin,
                    requestType: 'SOS Repeat False Alarm',
                    referenceNo: "SOS-{$alert->id}",
                    newStatus: 'Needs Review',
                    url: url("/sos/{$alert->id}"),
                );
            }
        }

        return $event;
    }

    public function resolve(SosAlert $alert, User $responder, ?string $notes = null): SosAlertEvent
    {
        if ($alert->status === 'false_alarm') {
            throw new \RuntimeException("Alert #{$alert->id} is already closed as a false alarm.");
        }

        $alert->update([
            'status'           => 'resolved',
            'resolved_at'      => now(),
            'resolved_by'      => $responder->id,
            'resolution_notes' => $notes,
        ]);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'resolved',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => $notes ? ['notes' => $notes] : null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }

    public function endByReporter(SosAlert $alert, Model $reporter): SosAlertEvent
    {
        if (in_array($alert->status, ['resolved', 'false_alarm'], true)) {
            throw new \RuntimeException("Alert #{$alert->id} is already closed.");
        }

        $alert->update([
            'status'           => 'resolved',
            'resolved_at'      => now(),
            'resolved_by'      => null,
            'resolution_notes' => 'Ended by reporting student.',
        ]);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'resolved',
            'actor_type'   => get_class($reporter),
            'actor_id'     => $reporter->getKey(),
            'payload'      => ['ended_by' => 'reporter'],
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }

    public function processEscalations(): int
    {
        $count = 0;

        $alerts = SosAlert::whereNotIn('status', ['resolved', 'false_alarm'])->get();

        foreach ($alerts as $alert) {
            $currentTier = SosEscalationTier::where('alert_type', $alert->alert_type)
                ->where('order', $alert->current_tier_order)
                ->first();

            // No tier configured for this order, or this is the final tier
            // (null timeout) — nothing further to advance to.
            if (! $currentTier || $currentTier->timeout_minutes === null) {
                continue;
            }

            // ->first()?->created_at (not ->value()) — value() returns the raw
            // DB scalar, bypassing model hydration, so it would NOT be a Carbon
            // instance and ->copy() below would fatal.
            $enteredAt = $alert->events()
                ->whereIn('type', ['triggered', 'escalated'])
                ->latest('created_at')
                ->first()?->created_at ?? $alert->triggered_at;

            if (now()->lessThan($enteredAt->copy()->addMinutes($currentTier->timeout_minutes))) {
                continue;
            }

            if ($this->escalate($alert)) {
                $count++;
            }
        }

        return $count;
    }

    private function escalate(SosAlert $alert): bool
    {
        $nextTier = SosEscalationTier::where('alert_type', $alert->alert_type)
            ->where('order', $alert->current_tier_order + 1)
            ->first();

        if (! $nextTier) {
            return false;
        }

        $alert->update(['status' => 'escalated', 'current_tier_order' => $nextTier->order]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'escalated',
            'actor_type'   => null,
            'actor_id'     => null,
            'payload'      => ['tier_order' => $nextTier->order],
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        NotifySosResponders::dispatch($alert->id, $nextTier->id);

        return true;
    }

    private function broadcastPayload(SosAlert $alert): array
    {
        return [
            'id'           => $alert->id,
            'alert_type'   => $alert->alert_type,
            'is_silent'    => $alert->is_silent,
            'status'       => $alert->status,
            'lat'          => $alert->lat,
            'lng'          => $alert->lng,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
        ];
    }
}
