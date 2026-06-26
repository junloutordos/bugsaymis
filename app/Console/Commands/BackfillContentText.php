<?php

namespace App\Console\Commands;

use App\Jobs\ExtractDocumentTextJob;
use App\Models\Issuance;
use App\Models\OedIssuance;
use Illuminate\Console\Command;

class BackfillContentText extends Command
{
    protected $signature   = 'issuances:backfill-content-text
                                {--force : Re-index records that already have content_text}';
    protected $description = 'Queue PDF text extraction for all Issuances and OED Issuances';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $issuances = Issuance::where('status', 'released')
            ->when(! $force, fn ($q) => $q->whereNull('content_text'))
            ->get(['id']);

        $this->info("Queuing {$issuances->count()} Issuances...");
        foreach ($issuances as $i) {
            ExtractDocumentTextJob::dispatch('issuance', $i->id);
        }

        $oed = OedIssuance::when(! $force, fn ($q) => $q->whereNull('content_text'))->get(['id']);

        $this->info("Queuing {$oed->count()} OED Issuances...");
        foreach ($oed as $o) {
            ExtractDocumentTextJob::dispatch('oed_issuance', $o->id);
        }

        $total = $issuances->count() + $oed->count();
        $this->info("Done — {$total} jobs dispatched to the queue.");

        return self::SUCCESS;
    }
}
