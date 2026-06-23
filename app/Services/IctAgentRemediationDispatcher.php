<?php

namespace App\Services;

use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentDevice;
use App\Models\IctEquipmentRemediationLog;
use App\Models\IctRemediationRule;

/**
 * Decides WHAT to remediate (server-side, auditable) — the agent only
 * executes what this returns. Only rules that are enabled AND auto_execute
 * ever produce an instruction; everything else stays inert until a human
 * flips that flag, per the self-healing safety boundary in the ICT Agent
 * plan (detection needs to be observed on real devices first).
 */
class IctAgentRemediationDispatcher
{
    private const LOW_DISK_FREE_PERCENT = 10;
    private const HIGH_PACKET_LOSS_PERCENT = 50;

    public function pendingActions(IctEquipmentDevice $device, array $payload): array
    {
        $rules = IctRemediationRule::where('enabled', true)->where('auto_execute', true)->get()->keyBy('metric_key');
        if ($rules->isEmpty()) {
            return [];
        }

        $actions = [];

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
        }
    }
}
