<?php

namespace Database\Seeders;

use App\Models\IctRemediationRule;
use Illuminate\Database\Seeder;

/**
 * Seeds the v1 self-healing rule set as enabled-but-NOT-auto-executing —
 * detection/scoring needs to be observed against real campus devices
 * before any of these are allowed to take automatic action. Flip
 * auto_execute to true per rule once that confidence exists.
 */
class IctRemediationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['metric_key' => 'service_down', 'condition' => 'status != Running', 'action' => 'service_restart'],
            ['metric_key' => 'printer_stuck', 'condition' => 'detected_error_state in (Offline, ServiceRequested) or stuck queue', 'action' => 'print_spooler_recovery'],
            ['metric_key' => 'disk_low', 'condition' => 'free_pct < 10', 'action' => 'temp_file_cleanup'],
            ['metric_key' => 'connectivity_degraded', 'condition' => 'packet_loss_pct > 50', 'action' => 'dns_flush'],
            ['metric_key' => 'disk_low_persistent', 'condition' => 'diag_bottleneck_disk_* open', 'action' => 'windows_maintenance_task'],
        ];

        foreach ($rules as $rule) {
            IctRemediationRule::firstOrCreate(
                ['metric_key' => $rule['metric_key']],
                $rule + ['auto_execute' => false, 'cooldown_minutes' => 60, 'enabled' => true]
            );
        }
    }
}
