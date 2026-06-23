<?php

namespace App\Services;

use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentDevice;
use App\Models\IctEquipmentHealthHistory;
use Carbon\Carbon;

/**
 * Turns a checkin's raw metrics + open alerts into a single 0-100
 * health_score and a risk_score/risk_tier that also factors in device
 * age/warranty. Rule-based weighted deductions, not a model — see the ICT
 * Agent plan's "AI Readiness" section for why that's deliberate for now.
 */
class IctAgentScoringService
{
    private const LOW_DISK_FREE_PERCENT = 15;
    private const LOW_RAM_FREE_PERCENT = 10;
    private const HIGH_CPU_USAGE_PERCENT = 85;
    private const HIGH_CPU_TEMP_C = 85;
    private const HIGH_PACKET_LOSS_PERCENT = 20;
    private const AGING_DEVICE_YEARS = 5;

    public function scoreAndStore(IctEquipmentDevice $device, array $payload, IctEquipmentHealthHistory $historyRow): void
    {
        $healthScore = $this->computeHealthScore($device, $payload);
        $riskScore = $this->computeRiskScore($device, $healthScore);
        $riskTier = $this->riskTier($riskScore);

        $historyRow->update(['health_score' => $healthScore, 'risk_score' => $riskScore]);

        $device->update([
            'health_score' => $healthScore,
            'risk_score' => $riskScore,
            'risk_tier' => $riskTier,
        ]);
    }

    private function computeHealthScore(IctEquipmentDevice $device, array $payload): int
    {
        $score = 100;

        foreach ($payload['disks'] ?? [] as $disk) {
            $total = $disk['total_gb'] ?? null;
            $free = $disk['free_gb'] ?? null;
            if ($total && $free !== null && ($free / $total) * 100 < self::LOW_DISK_FREE_PERCENT) {
                $score -= 10;
            }
        }

        $ramTotal = $payload['ram_total_mb'] ?? null;
        $ramFree = $payload['ram_free_mb'] ?? null;
        if ($ramTotal && $ramFree !== null && ($ramFree / $ramTotal) * 100 < self::LOW_RAM_FREE_PERCENT) {
            $score -= 15;
        }

        if (($payload['cpu_usage_pct'] ?? 0) > self::HIGH_CPU_USAGE_PERCENT) {
            $score -= 10;
        }

        if (($payload['cpu_temp_c'] ?? 0) > self::HIGH_CPU_TEMP_C) {
            $score -= 10;
        }

        $network = $payload['network'] ?? [];
        if (($network['packet_loss_pct'] ?? 0) > self::HIGH_PACKET_LOSS_PERCENT) {
            $score -= 10;
        }
        if (array_key_exists('link_up', $network) && $network['link_up'] === false) {
            $score -= 15;
        }

        $openAlerts = IctEquipmentAlert::where('device_id', $device->id)->where('status', 'open')->get(['severity']);
        foreach ($openAlerts as $alert) {
            $score -= $alert->severity === 'critical' ? 25 : 10;
        }

        return max(0, min(100, $score));
    }

    private function computeRiskScore(IctEquipmentDevice $device, int $healthScore): int
    {
        $risk = 100 - $healthScore;

        $equipment = $device->equipment;
        if ($equipment?->date_acquired && Carbon::parse($equipment->date_acquired)->diffInYears(now()) >= self::AGING_DEVICE_YEARS) {
            $risk += 10;
        }
        if ($equipment?->warranty_expires_at && Carbon::parse($equipment->warranty_expires_at)->isPast()) {
            $risk += 5;
        }

        return max(0, min(100, $risk));
    }

    private function riskTier(int $riskScore): string
    {
        return match (true) {
            $riskScore >= 75 => 'critical',
            $riskScore >= 50 => 'high',
            $riskScore >= 25 => 'medium',
            default => 'low',
        };
    }
}
