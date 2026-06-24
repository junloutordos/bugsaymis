<?php

namespace App\Console\Commands;

use App\Models\IctEquipmentManualRemediationRequest;
use Illuminate\Console\Command;

/**
 * IctAgentRemediationDispatcher marks a manual "Fix Now" request 'delivered'
 * the moment it's handed to the device on checkin, before the device has
 * actually run it — deliberately, so a missed/delayed result report doesn't
 * cause the same fix to be resent later. The gap: if the device never
 * reports back (crashes, goes offline, gets reinstalled mid-flight), that
 * request sits 'delivered' forever with no way to ever complete, and the
 * Agent Specs UI shows it as permanently "Queued…". This sweeps those out.
 */
class IctAgentExpireStaleRemediations extends Command
{
    protected $signature = 'ict-agent:expire-stale-remediations';
    protected $description = 'Mark manual remediation requests as failed if delivered but never reported back within the timeout window';

    // Generously above the ~20-min checkin interval — a healthy device
    // reports a result on its very next checkin, well inside this window.
    const STALE_AFTER_MINUTES = 60;

    public function handle(): int
    {
        $stale = IctEquipmentManualRemediationRequest::where('status', 'delivered')
            ->where('delivered_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->get();

        foreach ($stale as $request) {
            $request->update([
                'status' => 'failed',
                'result' => 'failed',
                'details' => 'Timed out — device never reported a result within ' . self::STALE_AFTER_MINUTES . ' minutes of delivery.',
                'completed_at' => now(),
            ]);
        }

        $this->info("Expired {$stale->count()} stale remediation request(s).");

        return self::SUCCESS;
    }
}
