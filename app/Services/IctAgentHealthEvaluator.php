<?php

namespace App\Services;

use App\Models\ICTPMSHistory;
use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentDevice;
use App\Models\PMS;

/**
 * Classifies a check-in payload into routine findings (logged into the
 * equipment's existing PMS history — no ticket, self-service territory)
 * versus actionable findings (tracked as open alerts until resolved or
 * escalated). Detect + report only — this never executes a fix itself.
 */
class IctAgentHealthEvaluator
{
    private const LOW_DISK_FREE_PERCENT = 15;
    private const LOW_RAM_FREE_PERCENT = 10;

    private const PRINTER_ROUTINE_STATES = ['Jammed', 'NoPaper', 'LowPaper', 'NoToner', 'LowToner', 'DoorOpen', 'OutputBinFull'];
    private const PRINTER_ACTIONABLE_STATES = ['Offline', 'ServiceRequested'];
    private const STUCK_QUEUE_THRESHOLD = 5;

    public function evaluate(IctEquipmentDevice $device, array $payload): void
    {
        $equipmentId = $device->equipment_id;
        if (! $equipmentId) {
            return;
        }

        $pmsId = $this->activePmsId($equipmentId);

        $this->evaluateDisks($equipmentId, $pmsId, $payload['disks'] ?? []);
        $this->evaluateRam($equipmentId, $pmsId, $payload);

        if (array_key_exists('printers', $payload)) {
            $this->evaluatePrinters($device, $equipmentId, $pmsId, $payload['printers'] ?? []);
        }

        if (array_key_exists('pnp_issues', $payload)) {
            $this->evaluatePnpIssues($device, $equipmentId, $payload['pnp_issues'] ?? []);
        }
    }

    private function evaluateDisks(int $equipmentId, ?int $pmsId, array $disks): void
    {
        foreach ($disks as $disk) {
            $total = $disk['total_gb'] ?? null;
            $free = $disk['free_gb'] ?? null;
            $drive = $disk['drive'] ?? '?';

            if (! $total || $free === null) {
                continue;
            }

            $percentFree = round(($free / $total) * 100, 1);
            if ($percentFree >= self::LOW_DISK_FREE_PERCENT) {
                continue;
            }

            $code = 'low_disk_' . preg_replace('/[^A-Za-z0-9]/', '', $drive);
            $message = "Low disk space on {$drive} — {$percentFree}% free ({$free} GB of {$total} GB).";
            $this->logRoutine($equipmentId, $pmsId, $code, $message);
        }
    }

    private function evaluateRam(int $equipmentId, ?int $pmsId, array $payload): void
    {
        $total = $payload['ram_total_mb'] ?? null;
        $free = $payload['ram_free_mb'] ?? null;

        if (! $total || $free === null) {
            return;
        }

        $percentFree = round(($free / $total) * 100, 1);
        if ($percentFree >= self::LOW_RAM_FREE_PERCENT) {
            return;
        }

        $message = "Low available memory — {$percentFree}% free ({$free} MB of {$total} MB).";
        $this->logRoutine($equipmentId, $pmsId, 'low_ram', $message);
    }

    private function evaluatePrinters(IctEquipmentDevice $device, int $equipmentId, ?int $pmsId, array $printers): void
    {
        $activeCodes = [];

        foreach ($printers as $printer) {
            $name = $printer['name'] ?? 'Unknown printer';
            $slug = preg_replace('/[^A-Za-z0-9]/', '', $name);
            $state = $printer['detected_error_state'] ?? null;
            $pendingJobs = $printer['pending_jobs'] ?? 0;

            if ($state && in_array($state, self::PRINTER_ROUTINE_STATES, true)) {
                $code = "printer_{$slug}_{$state}";
                $this->logRoutine($equipmentId, $pmsId, $code, "Printer \"{$name}\" needs attention: {$state}.");
            }

            if ($state && in_array($state, self::PRINTER_ACTIONABLE_STATES, true)) {
                $code = "printer_{$slug}_{$state}";
                $activeCodes[] = $code;
                $severity = $state === 'ServiceRequested' ? 'critical' : 'warning';
                $this->upsertAlert($device, $equipmentId, $code, $severity, "Printer \"{$name}\" is {$state}.");
            }

            if ($pendingJobs > self::STUCK_QUEUE_THRESHOLD) {
                $code = "printer_{$slug}_stuck_queue";
                $activeCodes[] = $code;
                $this->upsertAlert($device, $equipmentId, $code, 'warning', "Printer \"{$name}\" has {$pendingJobs} jobs stuck in queue.");
            }
        }

        $this->resolveStaleAlerts($device, 'printer_', $activeCodes);
    }

    private function evaluatePnpIssues(IctEquipmentDevice $device, int $equipmentId, array $pnpIssues): void
    {
        $activeCodes = [];

        foreach ($pnpIssues as $issue) {
            $name = $issue['device_name'] ?? 'Unknown device';
            $errorCode = $issue['error_code'] ?? '?';
            $code = 'pnp_' . preg_replace('/[^A-Za-z0-9]/', '', $name);
            $activeCodes[] = $code;

            $this->upsertAlert($device, $equipmentId, $code, 'warning',
                "Driver/device problem detected: \"{$name}\" (error code {$errorCode}).");
        }

        $this->resolveStaleAlerts($device, 'pnp_', $activeCodes);
    }

    /**
     * Equipment not enrolled in an active (Pending/Ongoing) PMS program has
     * no maintenance record to attach routine findings to, so those findings
     * are skipped entirely rather than logged unattached.
     */
    private function activePmsId(int $equipmentId): ?int
    {
        return PMS::whereHas('equipments', function ($q) use ($equipmentId) {
            $q->where('ict_equipments.id', $equipmentId);
        })->whereIn('status', ['Pending', 'Ongoing'])->value('id');
    }

    /**
     * Routine findings join the equipment's existing PMS history — deduped
     * per day per code so a 20-minute check-in interval doesn't spam the
     * timeline. Skipped entirely if the equipment has no active PMS program.
     */
    private function logRoutine(int $equipmentId, ?int $pmsId, string $code, string $message): void
    {
        if ($pmsId === null) {
            return;
        }

        $today = now()->toDateString();
        $tag = "[{$code}]";

        $alreadyLogged = ICTPMSHistory::where('equipment_id', $equipmentId)
            ->where('type', 'Agent Check')
            ->whereDate('pms_date', $today)
            ->where('description', 'like', "{$tag}%")
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        ICTPMSHistory::create([
            'ict_pms_id'   => $pmsId,
            'equipment_id' => $equipmentId,
            'pms_date'     => $today,
            'description'  => "{$tag} {$message}",
            'type'         => 'Agent Check',
            'created_by'   => null,
        ]);
    }

    private function upsertAlert(IctEquipmentDevice $device, int $equipmentId, string $code, string $severity, string $message): void
    {
        $existing = IctEquipmentAlert::where('device_id', $device->id)
            ->where('code', $code)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            $existing->update(['issue' => $message, 'severity' => $severity]);
            return;
        }

        IctEquipmentAlert::create([
            'device_id'    => $device->id,
            'code'         => $code,
            'equipment_id' => $equipmentId,
            'issue'        => $message,
            'severity'     => $severity,
            'status'       => 'open',
        ]);
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
