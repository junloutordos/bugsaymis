<?php

namespace Database\Seeders;

use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveCreditTransaction;
use App\Models\HR\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LeaveCreditsInitializationSeeder
 *
 * PURPOSE
 * -------
 * One-time seeder to migrate legacy / manually-tracked leave balances into the
 * system.  Run this ONCE after deploying the Leave Credits module.
 *
 * HOW TO USE
 * ----------
 * 1. Fill in the $initialBalances array below with each employee's carried-over
 *    balances as of the start of the current year (or the go-live date).
 * 2. Run: php artisan db:seed --class=LeaveCreditsInitializationSeeder
 * 3. This seeder is idempotent — re-running it will SKIP any row that already
 *    has a leave_credits entry for the given user/type/year.
 *
 * SAMPLE DATA FORMAT
 * ------------------
 * Each entry:
 *   'employee_id' => <users.id>,
 *   'year'        => <calendar year>,
 *   'balances'    => [
 *       'VL'  => <decimal>,   // Vacation Leave credits
 *       'SL'  => <decimal>,   // Sick Leave credits
 *       'CTO' => <decimal>,   // Compensatory Time Off (teaching staff only)
 *   ],
 *   'remarks'     => 'Opening balance migrated from HR records as of YYYY-MM-DD',
 *
 * NOTES
 * -----
 * - Use the employee's user ID (users.id), not their employee number.
 * - Balances represent CARRIED-OVER credits from previous years PLUS any
 *   credits already earned in the current year before go-live.
 * - All INITIAL entries are recorded as type = 'INITIAL' in
 *   leave_credit_transactions for a full audit trail.
 * - Teaching staff earning Service Credits should use the
 *   service_credit_records table instead (see ServiceCreditRecord model).
 */
class LeaveCreditsInitializationSeeder extends Seeder
{
    /**
     * ─── EDIT THIS ARRAY TO MATCH YOUR ACTUAL DATA ───────────────────────────
     *
     * Format: one entry per employee.
     */
    private array $initialBalances = [
        // Example entries — replace with actual data before running
        // [
        //     'employee_id' => 1,
        //     'year'        => 2026,
        //     'balances'    => ['VL' => 25.50, 'SL' => 18.75],
        //     'remarks'     => 'Opening balance migrated from HR records as of 2026-01-01',
        // ],
        // [
        //     'employee_id' => 2,
        //     'year'        => 2026,
        //     'balances'    => ['VL' => 10.00, 'SL' => 5.00, 'CTO' => 3.00],
        //     'remarks'     => 'Opening balance migrated from HR records as of 2026-01-01',
        // ],
    ];

    public function run(): void
    {
        if (empty($this->initialBalances)) {
            $this->command->warn('LeaveCreditsInitializationSeeder: No initial balances defined. Edit the $initialBalances array and re-run.');
            return;
        }

        $leaveTypes = LeaveType::whereIn('code', $this->collectLeaveCodes())
                               ->pluck('id', 'code');

        $hrUserId = User::where('name', 'like', '%Admin%')
                        ->orWhereHas('roles', fn ($q) => $q->where('name', 'HR'))
                        ->value('id');

        $skipped  = 0;
        $inserted = 0;

        DB::transaction(function () use ($leaveTypes, $hrUserId, &$skipped, &$inserted) {
            foreach ($this->initialBalances as $entry) {
                $userId = $entry['employee_id'];
                $year   = $entry['year'];

                foreach ($entry['balances'] as $code => $amount) {
                    if (! isset($leaveTypes[$code])) {
                        $this->command->warn("  Skipping unknown leave type '{$code}' for user {$userId}.");
                        continue;
                    }

                    $leaveTypeId = $leaveTypes[$code];

                    // Skip if already initialized
                    $exists = LeaveCredit::where('user_id', $userId)
                                        ->where('leave_type_id', $leaveTypeId)
                                        ->where('year', $year)
                                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    // Create the leave_credits row (entire amount goes into carried_over
                    // so the balance formula: earned + carried_over - used... = amount)
                    LeaveCredit::create([
                        'user_id'      => $userId,
                        'leave_type_id' => $leaveTypeId,
                        'year'         => $year,
                        'earned'       => 0,
                        'carried_over' => $amount,
                        'used'         => 0,
                        'forfeited'    => 0,
                        'monetized'    => 0,
                    ]);

                    // Record the INITIAL transaction for audit trail
                    LeaveCreditTransaction::create([
                        'user_id'      => $userId,
                        'leave_type_id' => $leaveTypeId,
                        'year'         => $year,
                        'type'         => 'INITIAL',
                        'amount'       => $amount,
                        'balance_after' => $amount,
                        'remarks'      => $entry['remarks'] ?? 'Opening balance — migrated from legacy records',
                        'recorded_by'  => $hrUserId,
                    ]);

                    $inserted++;
                }
            }
        });

        $this->command->info("Leave Credits Initialization: {$inserted} record(s) inserted, {$skipped} skipped (already exist).");
    }

    private function collectLeaveCodes(): array
    {
        return collect($this->initialBalances)
            ->flatMap(fn ($e) => array_keys($e['balances']))
            ->unique()
            ->values()
            ->all();
    }
}
