<?php

namespace App\Console\Commands\HR;

use App\Jobs\HR\GenerateDTRRecords;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class GenerateDailyDTR extends Command
{
    protected $signature = 'hr:dtr:daily
                            {--date= : Target date (Y-m-d). Defaults to yesterday.}';

    protected $description = 'Auto-generate DTR records for all active employees for the given date (default: yesterday).';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::yesterday()->toDateString();

        $users = User::employees()->where('status', 'active')
            ->pluck('id');

        if ($users->isEmpty()) {
            $this->info("No active employees found — nothing to do.");
            return self::SUCCESS;
        }

        $jobs = $users->map(fn ($id) => new GenerateDTRRecords($id, $date, $date))->all();

        Bus::batch($jobs)
            ->name("Daily DTR — {$date}")
            ->allowFailures()
            ->dispatch();

        $this->info("Dispatched DTR generation for {$users->count()} employees for {$date}.");
        Log::info("hr:dtr:daily dispatched", ['date' => $date, 'employees' => $users->count()]);

        return self::SUCCESS;
    }
}
