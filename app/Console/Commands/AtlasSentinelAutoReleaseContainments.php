<?php

namespace App\Console\Commands;

use App\Services\AtlasSentinelContainmentService;
use Illuminate\Console\Command;

class AtlasSentinelAutoReleaseContainments extends Command
{
    protected $signature = 'atlas-sentinel:auto-release-containments';

    protected $description = 'Auto-release contained devices whose incident timed out without confirmation';

    public function handle(AtlasSentinelContainmentService $service): int
    {
        $count = $service->autoReleaseExpired();
        $this->info("Auto-released {$count} expired containment incident(s).");

        return self::SUCCESS;
    }
}
