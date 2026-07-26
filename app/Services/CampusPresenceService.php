<?php

namespace App\Services;

use App\Services\HR\GeofenceService;
use App\Services\HR\NetworkTrustService;

/**
 * Domain-neutral "are you actually here" check shared by HR Online Punch and
 * the Teacher Attendance NFC/QR tap-in. Combines a GPS geofence check with a
 * trusted-campus-network check — either one passing is enough, since GPS can
 * be denied/coarse and network trust can be inconclusive on its own.
 */
class CampusPresenceService
{
    /**
     * Browser geolocation on devices without GPS (desktop PCs) falls back to
     * Wi-Fi/IP positioning that can be off by kilometers — a coarse fix whose
     * center happens to land inside a geofence zone proves nothing. Fixes
     * coarser than this are rejected unless the request comes from a trusted
     * campus network (which is server-observed and proves presence on its own).
     */
    public const MAX_LOCATION_ACCURACY_METERS = 250.0;

    public function __construct(
        private readonly GeofenceService $geofenceService,
        private readonly NetworkTrustService $networkTrustService,
    ) {}

    public function resolveLocationGate(?float $lat, ?float $lng, ?float $accuracy, ?string $ip): array
    {
        $geofence      = $this->geofenceService->resolve($lat, $lng);
        $networkStatus = $this->networkTrustService->resolve($ip)['status'];

        $status = match (true) {
            in_array($geofence['status'], ['outside', 'no_permission'], true) => $geofence['status'],
            // A coarse fix inside the zone proves nothing — unless the request
            // IP is a trusted campus network, which proves presence by itself.
            $geofence['status'] === 'inside'
                && ($accuracy === null || $accuracy > self::MAX_LOCATION_ACCURACY_METERS)
                && $networkStatus !== 'trusted' => 'coarse',
            default => 'ok', // inside with a precise fix, or geofencing unconfigured
        };

        return ['status' => $status, 'geofence' => $geofence, 'networkStatus' => $networkStatus];
    }
}
