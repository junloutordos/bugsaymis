<?php

namespace App\Console\Commands\OPCR;

use App\Models\PerformanceIndicator;
use App\Services\OPCR\OpcrIndicatorPropagationService;
use Illuminate\Console\Command;

class BackfillOpcrFromPerformanceIndicators extends Command
{
    protected $signature = 'opcr:backfill-from-performance-indicators';

    protected $description = 'Links every Performance Indicator tagged to a PSHS Program (Strategic Functions) into OPCR, for indicators created before auto-propagation existed. Idempotent.';

    public function handle(OpcrIndicatorPropagationService $service): int
    {
        $count = 0;

        PerformanceIndicator::with('agencyOutcome.parent')->chunkById(100, function ($indicators) use ($service, &$count) {
            foreach ($indicators as $indicator) {
                $service->syncFromPerformanceIndicator($indicator);
                $count++;
            }
        });

        $this->info("Processed {$count} Performance Indicator(s).");

        return self::SUCCESS;
    }
}
