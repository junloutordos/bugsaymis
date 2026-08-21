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
