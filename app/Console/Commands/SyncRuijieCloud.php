<?php

namespace App\Console\Commands;

use App\Models\NetworkScanLog;
use App\Services\NetworkInventory\NetworkDeviceSyncService;
use App\Services\NetworkInventory\RuijieCloudService;
use Illuminate\Console\Command;

class SyncRuijieCloud extends Command
{
    protected $signature   = 'network:sync-ruijie-cloud';
    protected $description = 'Fetch wireless clients from Ruijie Cloud API and sync to network inventory';

    public function handle(RuijieCloudService $cloud, NetworkDeviceSyncService $sync): int
    {
        if (! $cloud->isConfigured()) {
            $this->warn('Ruijie Cloud credentials not configured — skipping.');
            return self::SUCCESS;
        }

        $this->info('Fetching wireless clients from Ruijie Cloud...');

        $devices = $cloud->getWirelessClients();

        if (empty($devices)) {
            $this->warn('No devices returned from Ruijie Cloud.');
            NetworkScanLog::create([
                'source'          => 'ruijie_cloud',
                'scanned_at'      => now(),
                'devices_found'   => 0,
                'devices_online'  => 0,
                'status'          => 'partial',
                'error_message'   => 'No devices returned from API.',
            ]);
            return self::SUCCESS;
        }

        $log = $sync->syncFromRuijieCloud($devices);

        $this->info("Synced {$log->devices_found} device(s) from Ruijie Cloud in {$log->duration_ms}ms.");

        return self::SUCCESS;
    }
}
