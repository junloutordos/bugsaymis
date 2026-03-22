<?php

namespace App\Console\Commands\Payroll;

use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Accrues monthly leave credits for all active permanent/regular employees.
 *
 * Per CSC rules: 1.25 days Vacation Leave + 1.25 days Sick Leave per month served.
 * Run on the 1st of each month for the previous month's accrual.
 */
class AccrueMonthlyLeaveCredits extends Command
{
    protected $signature = 'payroll:accrue-leave
                            {--year=  : Year to accrue for. Defaults to current year.}
                            {--month= : Month (1-12) to accrue for. Defaults to current month.}';

    protected $description = 'Accrue 1.25 VL + 1.25 SL credits for all active permanent employees.';

    private const MONTHLY_ACCRUAL = 1.25;

    public function handle(): int
    {
        $year  = (int) ($this->option('year')  ?: now()->year);
        $month = (int) ($this->option('month') ?: now()->month);

        // Resolve VL and SL leave type IDs
        $vlId = LeaveType::where('code', 'VL')->value('id');
        $slId = LeaveType::where('code', 'SL')->value('id');

        if (! $vlId || ! $slId) {
            $this->error("VL or SL leave type not found in leave_types table.");
            return self::FAILURE;
        }

        // Active employees with a permanent/regular employment type
        $users = User::where('status', 'active')
            ->whereHas('employeeProfile', function ($q) {
                $q->whereIn('employment_type', ['permanent', 'regular']);
            })
            ->pluck('id');

        if ($users->isEmpty()) {
            $this->info("No eligible employees found.");
            return self::SUCCESS;
        }

        $accrued = 0;

        DB::transaction(function () use ($users, $vlId, $slId, $year, $accrued) {
            foreach ($users as $userId) {
                foreach ([$vlId, $slId] as $leaveTypeId) {
                    LeaveCredit::updateOrCreate(
                        ['user_id' => $userId, 'leave_type_id' => $leaveTypeId, 'year' => $year],
                        [],
                    );

                    LeaveCredit::where('user_id', $userId)
                        ->where('leave_type_id', $leaveTypeId)
                        ->where('year', $year)
                        ->increment('earned', self::MONTHLY_ACCRUAL);
                }
            }
        });

        $count = $users->count();
        $this->info("Accrued " . self::MONTHLY_ACCRUAL . " VL + " . self::MONTHLY_ACCRUAL . " SL for {$count} employees ({$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . ").");
        Log::info("payroll:accrue-leave complete", ['year' => $year, 'month' => $month, 'employees' => $count]);

        return self::SUCCESS;
    }
}
