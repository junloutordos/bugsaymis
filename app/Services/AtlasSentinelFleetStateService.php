<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class AtlasSentinelFleetStateService
{
    public const STALE_AFTER_MINUTES = 40;
    public const OFFLINE_AFTER_MINUTES = 120;

    // Herd guard: at most this many devices are offered an update per
    // window, so a fresh release can't make the whole fleet download a
    // ~100MB zip in the same checkin cycle. Throttled devices are simply
    // offered again on a later checkin.
    public const MAX_OFFERS_PER_WINDOW = 5;
    public const OFFER_WINDOW_MINUTES = 5;

    public function truncateVersion(?string $version): string
    {
        $version = trim((string) $version);

        if ($version === '') {
            return '';
        }

        return implode('.', array_slice(explode('.', $version), 0, 3));
    }

    public function shouldOfferUpdate(?string $latestVersion, ?string $reportedVersion): bool
    {
        $latest = $this->truncateVersion($latestVersion);
        $reported = $this->truncateVersion($reportedVersion);

        if ($latest === '') {
            return false;
        }

        if ($reported === '') {
            return true;
        }

        return version_compare($latest, $reported, '>');
    }

    public function tryReserveUpdateOfferSlot(string $version): bool
    {
        $windowSeconds = self::OFFER_WINDOW_MINUTES * 60;
        $window = (int) floor(now()->timestamp / $windowSeconds);
        $key = "atlas-sentinel:update-offers:{$version}:{$window}";

        // add() is a no-op when the key exists, so the counter survives; the
        // TTL outlives the window by one span to cover clock-edge increments.
        Cache::add($key, 0, $windowSeconds * 2);

        return Cache::increment($key) <= self::MAX_OFFERS_PER_WINDOW;
    }

    public function minutesSinceCheckin(?CarbonInterface $lastCheckinAt): ?int
    {
        if (! $lastCheckinAt) {
            return null;
        }

        return (int) floor($lastCheckinAt->diffInMinutes(now()));
    }

    public function classify(?CarbonInterface $lastCheckinAt, ?string $agentVersion, ?string $latestVersion): string
    {
        $minutesSince = $this->minutesSinceCheckin($lastCheckinAt);

        if ($minutesSince === null || $minutesSince > self::OFFLINE_AFTER_MINUTES) {
            return 'offline';
        }

        if ($minutesSince > self::STALE_AFTER_MINUTES) {
            return 'stale';
        }

        if ($this->truncateVersion($agentVersion) === '') {
            return 'unknown_version';
        }

        if ($this->shouldOfferUpdate($latestVersion, $agentVersion)) {
            return 'active_outdated';
        }

        return 'current';
    }
}
