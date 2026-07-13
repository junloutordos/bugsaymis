<?php

namespace App\Services\HR;

use App\Models\HR\OnlinePunchGeofenceZone;

class GeofenceService
{
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Resolve whether a coordinate falls inside any active geofence zone.
     * No active zones = feature not configured yet, so callers should let
     * the punch proceed rather than lock everyone out.
     */
    public function resolve(?float $lat, ?float $lng): array
    {
        $zones = OnlinePunchGeofenceZone::where('is_active', true)->get();

        if ($zones->isEmpty()) {
            return ['status' => 'unconfigured', 'distanceMeters' => null, 'zoneId' => null];
        }

        if ($lat === null || $lng === null) {
            return ['status' => 'no_permission', 'distanceMeters' => null, 'zoneId' => null];
        }

        $closest = null;

        foreach ($zones as $zone) {
            $distance = $this->haversineMeters($lat, $lng, (float) $zone->latitude, (float) $zone->longitude);

            if ($closest === null || $distance < $closest['distance']) {
                $closest = ['distance' => $distance, 'zone' => $zone];
            }

            if ($distance <= $zone->radius_meters) {
                return ['status' => 'inside', 'distanceMeters' => round($distance, 2), 'zoneId' => $zone->id];
            }
        }

        return [
            'status'         => 'outside',
            'distanceMeters' => round($closest['distance'], 2),
            'zoneId'         => $closest['zone']->id,
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
