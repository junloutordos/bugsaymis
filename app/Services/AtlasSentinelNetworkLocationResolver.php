<?php

namespace App\Services;

/**
 * Classifies a check-in as on/off/unknown-campus. Deliberately conservative -
 * a non-match is only ever "off_campus" when the device IS on some Wi-Fi
 * network (just not a campus one); a wired connection with no IP match is
 * "unknown" rather than "off_campus", since some campus ISPs have no static
 * IP to compare against. False accusations of leaving campus have real
 * consequences for the employee, so ambiguous cases never resolve to
 * off_campus.
 */
class AtlasSentinelNetworkLocationResolver
{
    public function resolve(?string $wifiSsid, ?string $requestIp): string
    {
        $campusSsids = config('atlas_sentinel.campus_ssids');
        $campusIps = config('atlas_sentinel.campus_ips');

        // Case-insensitive: the AP broadcasts "PSHS-CRC-Spectra" while config
        // had "PSHS-CRC-SPECTRA" — casing drift must not flag anyone off-campus.
        if ($wifiSsid && in_array(
            mb_strtolower(trim($wifiSsid)),
            array_map(fn ($ssid) => mb_strtolower($ssid), $campusSsids),
            true
        )) {
            return 'on_campus';
        }

        if ($requestIp && in_array($requestIp, $campusIps, true)) {
            return 'on_campus';
        }

        if ($wifiSsid) {
            return 'off_campus';
        }

        return 'unknown';
    }
}
