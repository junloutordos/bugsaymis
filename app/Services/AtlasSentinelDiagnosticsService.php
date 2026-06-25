<?php

namespace App\Services;

use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentDevice;
use App\Models\IctEquipmentHealthHistory;
use Carbon\Carbon;

/**
 * Correlates signals ACROSS checkins/history into diagnostic findings —
 * distinct from AtlasSentinelHealthEvaluator, which only classifies a single
 * checkin's payload. Writes into the same ict_equipment_alerts table with
 * "diag_*" codes, so escalation/resolution reuses the existing pipeline.
 *
 * Root-cause output is a short rule-based "probable_cause" string attached
 * to the alert, not a trained model — see the Atlas Sentinel plan's "AI
 * Readiness" section for why that's deliberate for v1.
 */
class AtlasSentinelDiagnosticsService
{
    private const SUSTAINED_CHECKINS = 3;
    private const HIGH_CPU_USAGE_PERCENT = 85;
    private const LOW_RAM_FREE_PERCENT = 10;
    private const LOW_DISK_FREE_PERCENT = 10;
    private const HIGH_PACKET_LOSS_PERCENT = 50;
    private const RECURRING_PNP_DAYS = 3;
    private const BATTERY_WEAR_WARNING_PERCENT = 20;
    private const STALE_PATCH_DAYS = 30;
    private const HIGH_EVENT_VOLUME = 10;
    private const FREQUENT_REBOOT_COUNT = 3;
    private const FREQUENT_REBOOT_WINDOW_DAYS = 7;

    /**
     * Called from the 20-min checkin() — after AtlasSentinelHealthEvaluator, so
     * any severity escalation here (e.g. recurring driver errors) is what
     * actually persists until the next cycle re-runs both in the same order.
     */
    public function diagnoseFastLoop(IctEquipmentDevice $device, array $payload): void
    {
        $this->detectBottlenecks($device, $payload);
        $this->detectConnectivityIssues($device, $payload);
        $this->escalateRecurringDriverIssues($device);
    }

    /**
     * Called from inventory-checkin() (daily) — hardware failure signals
     * and OS-health signals that only make sense at that cadence.
     */
    public function diagnoseInventoryLoop(IctEquipmentDevice $device, array $hardware, array $security, array $eventLogEntries): void
    {
        $this->detectHardwareFailureRisk($device, $hardware);
        $this->analyzeApplicationCrashes($device, $eventLogEntries);
        $this->assessOsHealth($device, $security, $eventLogEntries);
    }

    private function detectBottlenecks(IctEquipmentDevice $device, array $payload): void
    {
        $recent = IctEquipmentHealthHistory::where('device_id', $device->id)
            ->orderByDesc('recorded_at')
            ->limit(self::SUSTAINED_CHECKINS)
            ->get(['payload']);

        if ($recent->count() < self::SUSTAINED_CHECKINS) {
            return; // not enough history yet to call anything "sustained"
        }

        $allHighCpu = $recent->every(fn ($h) => ($h->payload['cpu_usage_pct'] ?? 0) > self::HIGH_CPU_USAGE_PERCENT);
        if ($allHighCpu) {
            $this->upsertAlert($device, 'diag_bottleneck_cpu', 'warning',
                'CPU usage has stayed above ' . self::HIGH_CPU_USAGE_PERCENT . '% for the last ' . self::SUSTAINED_CHECKINS . ' check-ins.',
                $this->probableCause($payload, exclude: 'cpu_usage_pct'));
        } else {
            $this->resolveAlert($device, 'diag_bottleneck_cpu');
        }

        $allLowRam = $recent->every(function ($h) {
            $total = $h->payload['ram_total_mb'] ?? null;
            $free = $h->payload['ram_free_mb'] ?? null;
            return $total && $free !== null && ($free / $total) * 100 < self::LOW_RAM_FREE_PERCENT;
        });
        if ($allLowRam) {
            $this->upsertAlert($device, 'diag_bottleneck_ram', 'warning',
                'Available memory has stayed below ' . self::LOW_RAM_FREE_PERCENT . '% free for the last ' . self::SUSTAINED_CHECKINS . ' check-ins.',
                $this->probableCause($payload, exclude: 'ram_free_mb'));
        } else {
            $this->resolveAlert($device, 'diag_bottleneck_ram');
        }

        $this->detectSustainedLowDisk($device, $recent, $payload);
    }

    private function detectSustainedLowDisk(IctEquipmentDevice $device, $recentHistory, array $payload): void
    {
        $drivesNowLow = [];
        foreach ($payload['disks'] ?? [] as $disk) {
            $drive = $disk['drive'] ?? null;
            $total = $disk['total_gb'] ?? null;
            $free = $disk['free_gb'] ?? null;
            if (! $drive || ! $total || $free === null) {
                continue;
            }
            if (($free / $total) * 100 < self::LOW_DISK_FREE_PERCENT) {
                $drivesNowLow[] = $drive;
            }
        }

        $activeCodes = [];
        foreach ($drivesNowLow as $drive) {
            $sustained = $recentHistory->every(function ($h) use ($drive) {
                foreach ($h->payload['disks'] ?? [] as $disk) {
                    if (($disk['drive'] ?? null) !== $drive) {
                        continue;
                    }
                    $total = $disk['total_gb'] ?? null;
                    $free = $disk['free_gb'] ?? null;
                    return $total && $free !== null && ($free / $total) * 100 < self::LOW_DISK_FREE_PERCENT;
                }
                return false;
            });

            if ($sustained) {
                $code = 'diag_bottleneck_disk_' . preg_replace('/[^A-Za-z0-9]/', '', $drive);
                $activeCodes[] = $code;
                $this->upsertAlert($device, $code, 'warning',
                    "Disk {$drive} has stayed below " . self::LOW_DISK_FREE_PERCENT . '% free for the last ' . self::SUSTAINED_CHECKINS . ' check-ins.',
                    null);
            }
        }

        $this->resolveStaleAlerts($device, 'diag_bottleneck_disk_', $activeCodes);
    }

    private function detectConnectivityIssues(IctEquipmentDevice $device, array $payload): void
    {
        $network = $payload['network'] ?? [];
        $linkUp = $network['link_up'] ?? null;
        $packetLoss = $network['packet_loss_pct'] ?? null;

        if ($linkUp === false) {
            $this->upsertAlert($device, 'diag_connectivity', 'critical', 'Network link is down.', null);
            return;
        }

        if ($packetLoss !== null && $packetLoss > self::HIGH_PACKET_LOSS_PERCENT) {
            $this->upsertAlert($device, 'diag_connectivity', 'warning',
                "High packet loss to the gateway ({$packetLoss}%).", null);
            return;
        }

        $this->resolveAlert($device, 'diag_connectivity');

        // Frequent on/off-campus flapping isn't detected here — that needs a
        // dedicated network-location change-log, which doesn't exist yet
        // (only the single last-changed timestamp is tracked on the device).
    }

    /**
     * The evaluator resets pnp_* alerts to 'warning' on every checkin
     * (it just re-detects, doesn't track age) — this escalates them back to
     * 'critical' once they've been open long enough to call "recurring",
     * since this method always runs after the evaluator within checkin().
     */
    private function escalateRecurringDriverIssues(IctEquipmentDevice $device): void
    {
        IctEquipmentAlert::where('device_id', $device->id)
            ->where('status', 'open')
            ->where('code', 'like', 'pnp_%')
            ->where('created_at', '<=', now()->subDays(self::RECURRING_PNP_DAYS))
            ->update(['severity' => 'critical']);
    }

    private function detectHardwareFailureRisk(IctEquipmentDevice $device, array $hardware): void
    {
        $activeCodes = [];

        foreach ($hardware['disks'] ?? [] as $disk) {
            if (($disk['smart_status'] ?? null) === 'failing') {
                $drive = preg_replace('/[^A-Za-z0-9]/', '', $disk['drive'] ?? 'unknown');
                $code = "diag_smart_failing_{$drive}";
                $activeCodes[] = $code;
                $this->upsertAlert($device, $code, 'critical',
                    "SMART predict-failure flag is set on disk {$disk['drive']} — schedule replacement.", null);
            }
        }
        $this->resolveStaleAlerts($device, 'diag_smart_failing_', $activeCodes);

        $battery = $hardware['battery'] ?? null;
        $design = $battery['design_capacity_mwh'] ?? null;
        $full = $battery['full_charge_capacity_mwh'] ?? null;
        if ($design && $full) {
            $wearPct = round(100 - ($full / $design * 100), 1);
            if ($wearPct > self::BATTERY_WEAR_WARNING_PERCENT) {
                $this->upsertAlert($device, 'diag_battery_wear', 'warning',
                    "Battery has degraded {$wearPct}% from its design capacity.", null);
            } else {
                $this->resolveAlert($device, 'diag_battery_wear');
            }
        }
    }

    private function analyzeApplicationCrashes(IctEquipmentDevice $device, array $eventLogEntries): void
    {
        $crashEventIds = ['1000', '1001'];
        $bySource = [];

        foreach ($eventLogEntries as $entry) {
            if (($entry['channel'] ?? null) !== 'Application') {
                continue;
            }
            if (! in_array($entry['event_id'] ?? null, $crashEventIds, true)) {
                continue;
            }
            $source = $entry['source'] ?? 'Unknown';
            $bySource[$source] = $entry;
        }

        foreach ($bySource as $source => $entry) {
            $slug = preg_replace('/[^A-Za-z0-9]/', '', $source);
            $code = "diag_app_crash_{$slug}";
            $today = now()->toDateString();

            $alreadyToday = IctEquipmentAlert::where('device_id', $device->id)
                ->where('code', $code)
                ->whereDate('updated_at', $today)
                ->exists();

            if ($alreadyToday) {
                continue;
            }

            $this->upsertAlert($device, $code, 'warning',
                "\"{$source}\" reported a crash (Event ID {$entry['event_id']}).", null);
        }
    }

    private function assessOsHealth(IctEquipmentDevice $device, array $security, array $eventLogEntries): void
    {
        $lastUpdate = $security['last_update_installed_at'] ?? null;
        if ($lastUpdate && Carbon::parse($lastUpdate)->diffInDays(now()) > self::STALE_PATCH_DAYS) {
            $this->upsertAlert($device, 'diag_os_patch_stale', 'warning',
                "No Windows updates installed in over " . self::STALE_PATCH_DAYS . ' days.', null);
        } else {
            $this->resolveAlert($device, 'diag_os_patch_stale');
        }

        $criticalEventCount = count(array_filter($eventLogEntries, fn ($e) =>
            ($e['channel'] ?? null) === 'System' && in_array($e['level'] ?? null, ['Critical', 'Error'], true)));

        if ($criticalEventCount >= self::HIGH_EVENT_VOLUME) {
            $this->upsertAlert($device, 'diag_os_event_volume', 'warning',
                "{$criticalEventCount} critical/error System events logged in the last day.", null);
        } else {
            $this->resolveAlert($device, 'diag_os_event_volume');
        }

        $this->detectFrequentReboots($device);
    }

    /**
     * A reboot shows up as uptime_seconds dropping below what the previous
     * checkin already reported, rather than growing as expected.
     */
    private function detectFrequentReboots(IctEquipmentDevice $device): void
    {
        $rows = IctEquipmentHealthHistory::where('device_id', $device->id)
            ->where('recorded_at', '>=', now()->subDays(self::FREQUENT_REBOOT_WINDOW_DAYS))
            ->orderBy('recorded_at')
            ->get(['payload']);

        $reboots = 0;
        $previousUptime = null;
        foreach ($rows as $row) {
            $uptime = $row->payload['uptime_seconds'] ?? null;
            if ($uptime !== null && $previousUptime !== null && $uptime < $previousUptime) {
                $reboots++;
            }
            $previousUptime = $uptime ?? $previousUptime;
        }

        if ($reboots >= self::FREQUENT_REBOOT_COUNT) {
            $this->upsertAlert($device, 'diag_os_frequent_reboots', 'warning',
                "{$reboots} unexpected reboots detected in the last " . self::FREQUENT_REBOOT_WINDOW_DAYS . ' days.', null);
        } else {
            $this->resolveAlert($device, 'diag_os_frequent_reboots');
        }
    }

    /**
     * Best-effort "what else was going on" note attached to a finding —
     * rule-based correlation within the same payload, not causal inference.
     */
    private function probableCause(array $payload, string $exclude): ?string
    {
        $notes = [];

        if ($exclude !== 'cpu_usage_pct' && ($payload['cpu_usage_pct'] ?? 0) > self::HIGH_CPU_USAGE_PERCENT) {
            $notes[] = 'high CPU usage';
        }
        if ($exclude !== 'ram_free_mb') {
            $total = $payload['ram_total_mb'] ?? null;
            $free = $payload['ram_free_mb'] ?? null;
            if ($total && $free !== null && ($free / $total) * 100 < self::LOW_RAM_FREE_PERCENT) {
                $notes[] = 'low available memory';
            }
        }

        return $notes ? 'Coincided with ' . implode(' and ', $notes) . '.' : null;
    }

    private function upsertAlert(IctEquipmentDevice $device, string $code, string $severity, string $message, ?string $probableCause): void
    {
        $existing = IctEquipmentAlert::where('device_id', $device->id)
            ->where('code', $code)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            $existing->update(['issue' => $message, 'severity' => $severity, 'probable_cause' => $probableCause]);
            return;
        }

        IctEquipmentAlert::create([
            'device_id' => $device->id,
            'code' => $code,
            'equipment_id' => $device->equipment_id,
            'issue' => $message,
            'severity' => $severity,
            'status' => 'open',
            'probable_cause' => $probableCause,
        ]);
    }

    private function resolveAlert(IctEquipmentDevice $device, string $code): void
    {
        IctEquipmentAlert::where('device_id', $device->id)
            ->where('code', $code)
            ->where('status', 'open')
            ->update(['status' => 'resolved']);
    }

    private function resolveStaleAlerts(IctEquipmentDevice $device, string $codePrefix, array $activeCodes): void
    {
        IctEquipmentAlert::where('device_id', $device->id)
            ->where('status', 'open')
            ->where('code', 'like', "{$codePrefix}%")
            ->whereNotIn('code', $activeCodes ?: ['__none__'])
            ->update(['status' => 'resolved']);
    }
}
