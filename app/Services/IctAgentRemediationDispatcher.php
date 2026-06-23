<?php

namespace App\Services;

use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentDevice;
use App\Models\IctEquipmentManualRemediationRequest;
use App\Models\IctEquipmentRemediationLog;
use App\Models\IctRemediationRule;

/**
 * Decides WHAT to remediate (server-side, auditable) — the agent only
 * executes what this returns. Rule-based actions only fire when a rule is
 * enabled AND auto_execute, per the self-healing safety boundary in the ICT
 * Agent plan (detection needs to be observed on real devices first). Manual
 * "Fix Now" requests (admin-triggered, see IctEquipmentManualRemediationRequest)
 * bypass that gate entirely since a human explicitly asked for it.
 */
class IctAgentRemediationDispatcher
{
    private const LOW_DISK_FREE_PERCENT = 10;
    private const HIGH_PACKET_LOSS_PERCENT = 50;
    private const MANUAL_TRIGGER_PREFIX = 'manual:';

    public function pendingActions(IctEquipmentDevice $device, array $payload): array
    {
        $actions = [];

        $this->addPendingManualRequests($actions, $device);

        $rules = IctRemediationRule::where('enabled', true)->where('auto_execute', true)->get()->keyBy('metric_key');
        if ($rules->isEmpty()) {
            return $actions;
        }

        if ($rule = $rules->get('service_down')) {
            foreach ($payload['services'] ?? [] as $service) {
                $status = $service['status'] ?? null;
                $name = $service['name'] ?? null;
                if ($name && $status && $status !== 'Running' && $status !== 'NotInstalled') {
                    $this->addIfNotCoolingDown($actions, $device, $rule, $name);
                }
            }
        }

        if ($rule = $rules->get('printer_stuck')) {
            $this->addAlertTargetsByPrefix($actions, $device, $rule, 'printer_');
        }

        if ($rule = $rules->get('disk_low')) {
            foreach ($payload['disks'] ?? [] as $disk) {
                $drive = $disk['drive'] ?? null;
                $total = $disk['total_gb'] ?? null;
                $free = $disk['free_gb'] ?? null;
                if ($drive && $total && $free !== null && ($free / $total) * 100 < self::LOW_DISK_FREE_PERCENT) {
                    $this->addIfNotCoolingDown($actions, $device, $rule, $drive);
                }
            }
        }

        if ($rule = $rules->get('connectivity_degraded')) {
            $packetLoss = $payload['network']['packet_loss_pct'] ?? null;
            if ($packetLoss !== null && $packetLoss > self::HIGH_PACKET_LOSS_PERCENT) {
                $this->addIfNotCoolingDown($actions, $device, $rule, 'default');
            }
        }

        if ($rule = $rules->get('disk_low_persistent')) {
            $this->addAlertTargetsByPrefix($actions, $device, $rule, 'diag_bottleneck_disk_', stripPrefix: true);
        }

        return $actions;
    }

    /**
     * Pending manual requests are delivered exactly once — marked
     * 'delivered' here so a missed/delayed result report doesn't cause the
     * same fix to be resent on a later checkin.
     */
    private function addPendingManualRequests(array &$actions, IctEquipmentDevice $device): void
    {
        $pending = IctEquipmentManualRemediationRequest::where('device_id', $device->id)
            ->where('status', 'pending')
            ->get();

        foreach ($pending as $request) {
            $actions[] = [
                'action' => $request->action,
                'target' => $request->target,
                'trigger_code' => self::MANUAL_TRIGGER_PREFIX . $request->id,
            ];
            $request->update(['status' => 'delivered', 'delivered_at' => now()]);
        }
    }

    private function addAlertTargetsByPrefix(array &$actions, IctEquipmentDevice $device, IctRemediationRule $rule, string $prefix, bool $stripPrefix = false): void
    {
        $codes = IctEquipmentAlert::where('device_id', $device->id)
            ->where('status', 'open')
            ->where('code', 'like', "{$prefix}%")
            ->pluck('code');

        foreach ($codes as $code) {
            $target = $stripPrefix ? substr($code, strlen($prefix)) : $code;
            $this->addIfNotCoolingDown($actions, $device, $rule, $target);
        }
    }

    private function addIfNotCoolingDown(array &$actions, IctEquipmentDevice $device, IctRemediationRule $rule, string $target): void
    {
        $recentAttempt = IctEquipmentRemediationLog::where('device_id', $device->id)
            ->where('action', $rule->action)
            ->where('target', $target)
            ->where('executed_at', '>=', now()->subMinutes($rule->cooldown_minutes))
            ->exists();

        if ($recentAttempt) {
            return;
        }

        $actions[] = ['action' => $rule->action, 'target' => $target, 'trigger_code' => $rule->metric_key];
    }

    public function recordResults(IctEquipmentDevice $device, array $results): void
    {
        foreach ($results as $result) {
            IctEquipmentRemediationLog::create([
                'device_id' => $device->id,
                'action' => $result['action'] ?? 'unknown',
                'target' => $result['target'] ?? null,
                'trigger_code' => $result['trigger_code'] ?? null,
                'result' => $result['result'] ?? 'failed',
                'details' => $result['details'] ?? null,
                'executed_at' => now(),
            ]);

            $triggerCode = $result['trigger_code'] ?? null;
            if ($triggerCode && str_starts_with($triggerCode, self::MANUAL_TRIGGER_PREFIX)) {
                $requestId = (int) substr($triggerCode, strlen(self::MANUAL_TRIGGER_PREFIX));
                IctEquipmentManualRemediationRequest::where('id', $requestId)
                    ->where('device_id', $device->id)
                    ->update([
                        'status' => ($result['result'] ?? 'failed') === 'success' ? 'completed' : 'failed',
                        'result' => $result['result'] ?? 'failed',
                        'details' => $result['details'] ?? null,
                        'completed_at' => now(),
                    ]);
            }
        }
    }
}
