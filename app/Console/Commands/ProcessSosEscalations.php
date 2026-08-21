<?php

namespace App\Console\Commands;

use App\Services\Sos\SosAlertService;
use Illuminate\Console\Command;

class ProcessSosEscalations extends Command
{
    protected $signature = 'sos:process-escalations';

    protected $description = 'Advance SOS alerts past their current tier timeout to the next escalation tier';

    public function handle(SosAlertService $service): int
    {
        $count = $service->processEscalations();
        $this->info("Escalated {$count} SOS alert(s).");

        return self::SUCCESS;
    }
}
