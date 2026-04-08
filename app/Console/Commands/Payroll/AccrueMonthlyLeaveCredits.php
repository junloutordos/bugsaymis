<?php

namespace App\Console\Commands\Payroll;

use App\Services\HR\LeaveCreditService;
use Illuminate\Console\Command;

/**
 * Accrues monthly leave credits for all active Non-Teaching employees.
 *
 * Per CSC Rule XVI: 1.25 days VL + 1.25 days SL per month served.
 * Teaching personnel do NOT accrue VL/SL — they use Service Credits instead.
 *
 * Run on the 1st of each month (see routes/console.php).
 */
class AccrueMonthlyLeaveCredits extends Command
{
    protected $signature = 'payroll:accrue-leave
                            {--year=  : Year to accrue for (default: current year)}
                            {--month= : Month (1-12) to accrue for (default: current month)}';

    protected $description = 'Accrue 1.25 VL + 1.25 SL credits for all active Non-Teaching employees.';

    public function handle(LeaveCreditService $service): int
    {
        $year  = (int) ($this->option('year')  ?: now()->year);
        $month = (int) ($this->option('month') ?: now()->month);

        $this->info("Accruing leave credits for {$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . '…');

        $count = $service->accrueMonthlyCredits($year, $month);

        $this->info("Done. Accrued 1.25 VL + 1.25 SL for {$count} employee(s).");

        return self::SUCCESS;
    }
}
