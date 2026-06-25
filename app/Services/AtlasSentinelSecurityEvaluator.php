<?php

namespace App\Services;

use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentDevice;
use App\Models\IctEquipmentSecurityStatus;
use App\Models\IctSoftwarePolicy;
use Illuminate\Support\Str;

/**
 * Security posture for one device's daily inventory-checkin — same
 * routine/alert pattern as AtlasSentinelHealthEvaluator, scoped to AV/firewall/
 * patch/unauthorized-software signals instead of hardware. Computes
 * unauthorized_software_count + security_score into the row
 * inventoryCheckin() already wrote the raw AV/firewall/update fields into.
 */
class AtlasSentinelSecurityEvaluator
{
    private const STALE_PATCH_WARNING_COUNT = 10;

    public function evaluate(IctEquipmentDevice $device, array $installedSoftware, array $security): void
    {
        $unauthorized = $this->matchUnauthorizedSoftware($installedSoftware);
        $securityScore = $this->computeSecurityScore($security, count($unauthorized));

        IctEquipmentSecurityStatus::where('device_id', $device->id)->update([
            'unauthorized_software_count' => count($unauthorized),
            'security_score' => $securityScore,
        ]);

        $this->generateAlerts($device, $security, $unauthorized);
    }

    /**
     * Pattern match via Str::is() (supports * wildcards) against the
     * admin-maintained block list — flagged/allowed entries aren't acted
     * on here, only explicit "blocked" matches.
     */
    private function matchUnauthorizedSoftware(array $installedSoftware): array
    {
        $blockedPatterns = IctSoftwarePolicy::where('policy', 'blocked')->pluck('name_pattern');
        if ($blockedPatterns->isEmpty()) {
            return [];
        }

        $matches = [];
        foreach ($installedSoftware as $software) {
            $name = $software['name'] ?? null;
            if (! $name) {
                continue;
            }
            foreach ($blockedPatterns as $pattern) {
                if (Str::is($pattern, $name)) {
                    $matches[] = $name;
                    break;
                }
            }
        }

        return $matches;
    }

    private function computeSecurityScore(array $security, int $unauthorizedCount): int
    {
        $score = 100;

        if (($security['antivirus_enabled'] ?? null) === false) {
            $score -= 30;
        }
        // No granular "definitions last updated" timestamp is collected —
        // antivirus_up_to_date is the agent's own productState decode, used
        // here as the available proxy for "AV is stale".
        if (($security['antivirus_up_to_date'] ?? null) === false) {
            $score -= 15;
        }
        if (($security['firewall_enabled'] ?? null) === false) {
            $score -= 20;
        }
        if (($security['pending_updates_count'] ?? 0) > self::STALE_PATCH_WARNING_COUNT) {
            $score -= 10;
        }
        if ($security['reboot_required'] ?? false) {
            $score -= 10;
        }
        $score -= min(20, $unauthorizedCount * 5);

        return max(0, min(100, $score));
    }

    private function generateAlerts(IctEquipmentDevice $device, array $security, array $unauthorized): void
    {
        $this->toggleAlert($device, 'sec_antivirus_disabled',
            ($security['antivirus_enabled'] ?? null) === false, 'critical', 'Antivirus is disabled.');

        $this->toggleAlert($device, 'sec_antivirus_stale',
            ($security['antivirus_up_to_date'] ?? null) === false, 'warning', 'Antivirus definitions are out of date.');

        $this->toggleAlert($device, 'sec_firewall_disabled',
            ($security['firewall_enabled'] ?? null) === false, 'critical', 'Windows Firewall is disabled.');

        $this->toggleAlert($device, 'sec_unauthorized_software',
            ! empty($unauthorized), 'warning',
            'Unauthorized software detected: ' . implode(', ', array_slice($unauthorized, 0, 5))
                . (count($unauthorized) > 5 ? ' (+' . (count($unauthorized) - 5) . ' more)' : '') . '.');
    }

    private function toggleAlert(IctEquipmentDevice $device, string $code, bool $active, string $severity, string $message): void
    {
        $existing = IctEquipmentAlert::where('device_id', $device->id)->where('code', $code)->where('status', 'open')->first();

        if (! $active) {
            $existing?->update(['status' => 'resolved']);
            return;
        }

        if ($existing) {
            $existing->update(['issue' => $message, 'severity' => $severity]);
            return;
        }

        IctEquipmentAlert::create([
            'device_id' => $device->id,
            'code' => $code,
            'equipment_id' => $device->equipment_id,
            'issue' => $message,
            'severity' => $severity,
            'status' => 'open',
        ]);
    }
}
