<?php

namespace App\Services\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveCreditTransaction;
use App\Models\HR\LeaveType;
use App\Models\HR\ServiceCreditRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LeaveCreditService
{
    // emp_category values that are considered Teaching personnel
    private const TEACHING_CATEGORIES = ['Plantilla Teaching', 'COS Teaching'];

    // VL and SL monthly accrual rate for Non-Teaching staff (CSC Rule XVI)
    private const MONTHLY_ACCRUAL = 1.25;

    // Division acronyms whose teaching chiefs also earn VL/SL (special case)
    private const SPECIAL_CHIEF_DIVISIONS = ['SSD', 'CID'];

    // ── Employee Type Helpers ─────────────────────────────────────────────────

    public function isTeaching(User $user): bool
    {
        return in_array($user->emp_category, self::TEACHING_CATEGORIES, true);
    }

    public function isNonTeaching(User $user): bool
    {
        return ! $this->isTeaching($user);
    }

    /**
     * Teaching faculty who are the active division chief of SSD or CID.
     * Special case: they earn VL + SL in addition to their teaching benefits.
     */
    public function isSpecialDivisionChief(User $user): bool
    {
        if (! $this->isTeaching($user)) {
            return false;
        }

        return Division::whereIn('acronym', self::SPECIAL_CHIEF_DIVISIONS)
            ->where('division_chief_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Return the set of special division chief user IDs for SSD and CID.
     * Used by controllers to pass the list to the frontend.
     */
    public function specialDivisionChiefIds(): array
    {
        $chiefIds = Division::whereIn('acronym', self::SPECIAL_CHIEF_DIVISIONS)
            ->where('status', 'active')
            ->pluck('division_chief_id')
            ->toArray();

        return User::whereIn('id', $chiefIds)
            ->whereIn('emp_category', self::TEACHING_CATEGORIES)
            ->pluck('id')
            ->toArray();
    }

    // ── 1. getEmployeeLeaveBalance ────────────────────────────────────────────

    /**
     * Return current leave balances for a user in a given year.
     *
     * Returns a keyed array:
     *   ['VL' => 15.00, 'SL' => 10.00, ...]
     *
     * For Teaching staff, also includes service_credits (sum of approved,
     * active ServiceCreditRecord days).
     */
    public function getEmployeeLeaveBalance(int $userId, ?int $year = null): array
    {
        $year ??= now()->year;
        $user   = User::findOrFail($userId);

        $credits = LeaveCredit::with('leaveType')
            ->where('user_id', $userId)
            ->where('year', $year)
            ->get();

        $balances = [];
        foreach ($credits as $credit) {
            $balances[$credit->leaveType->code] = [
                'leave_type_id' => $credit->leave_type_id,
                'earned'        => (float) $credit->earned,
                'carried_over'  => (float) $credit->carried_over,
                'used'          => (float) $credit->used,
                'forfeited'     => (float) $credit->forfeited,
                'monetized'     => (float) $credit->monetized,
                'balance'       => (float) $credit->balance,
            ];
        }

        // Teaching staff: aggregate approved service credits not yet consumed (separate from CTO)
        if ($this->isTeaching($user)) {
            $serviceDays = ServiceCreditRecord::active()
                ->where('user_id', $userId)
                ->sum('days_equivalent');

            $balances['SC'] = ['balance' => (float) $serviceDays, 'is_service_credit' => true];
        }

        return $balances;
    }

    // ── 2. initializeLeaveCredits ─────────────────────────────────────────────

    /**
     * Set opening balances for an employee (migration from legacy records).
     *
     * $values example: ['VL' => 25.50, 'SL' => 18.75]
     *
     * Each leave type is inserted into leave_credits (carried_over column) and
     * logged as an INITIAL transaction.  Throws if the row already has credits
     * for the year UNLESS $force = true.
     *
     * @throws RuntimeException when credits already exist and $force is false
     */
    public function initializeLeaveCredits(
        int    $userId,
        array  $values,
        string $remarks,
        int    $year      = null,
        bool   $force     = false,
        ?int   $recordedBy = null,
    ): void {
        $year ??= now()->year;

        DB::transaction(function () use ($userId, $values, $remarks, $year, $force, $recordedBy) {
            $leaveTypes = LeaveType::whereIn('code', array_keys($values))->pluck('id', 'code');

            foreach ($values as $code => $amount) {
                $amount = (float) $amount;

                if (! isset($leaveTypes[$code])) {
                    throw new RuntimeException("Unknown leave type code: {$code}");
                }

                $leaveTypeId = $leaveTypes[$code];

                $existing = LeaveCredit::where('user_id', $userId)
                    ->where('leave_type_id', $leaveTypeId)
                    ->where('year', $year)
                    ->first();

                if ($existing && ! $force) {
                    throw new RuntimeException(
                        "Leave credits for {$code} / {$year} already exist for user {$userId}. Use \$force=true to override."
                    );
                }

                if ($existing) {
                    // Forced re-initialization: adjust carried_over to new value
                    $delta = $amount - (float) $existing->carried_over;
                    $existing->increment('carried_over', $delta);
                    $credit = $existing->fresh();
                } else {
                    $credit = LeaveCredit::create([
                        'user_id'       => $userId,
                        'leave_type_id' => $leaveTypeId,
                        'year'          => $year,
                        'earned'        => 0,
                        'carried_over'  => $amount,
                        'used'          => 0,
                        'forfeited'     => 0,
                        'monetized'     => 0,
                    ]);
                }

                LeaveCreditTransaction::create([
                    'user_id'       => $userId,
                    'leave_type_id' => $leaveTypeId,
                    'year'          => $year,
                    'type'          => 'INITIAL',
                    'amount'        => $amount,
                    'balance_after' => (float) $credit->balance,
                    'remarks'       => $remarks,
                    'recorded_by'   => $recordedBy,
                ]);
            }
        });
    }

    // ── 3. accrueMonthlyCredits ───────────────────────────────────────────────

    /**
     * Accrue 1.25 VL + 1.25 SL for all active Non-Teaching employees.
     *
     * Should be triggered by a scheduled command on the 1st of each month.
     * Idempotent: skips users who already have an ACCRUAL transaction for the
     * current month (prevents double-accrual on re-run).
     *
     * Returns the number of users accrued.
     */
    public function accrueMonthlyCredits(?int $year = null, ?int $month = null): int
    {
        $year  ??= now()->year;
        $month ??= now()->month;

        $vlType = LeaveType::where('code', 'VL')->firstOrFail();
        $slType = LeaveType::where('code', 'SL')->firstOrFail();

        // Non-Teaching personnel + Teaching faculty who are SSD/CID division chiefs
        $specialChiefIds = $this->specialDivisionChiefIds();
        $nonTeaching     = User::whereNotNull('emp_category')
            ->whereNotIn('emp_category', self::TEACHING_CATEGORIES)
            ->get();
        $specialChiefs   = $specialChiefIds
            ? User::whereIn('id', $specialChiefIds)->get()
            : collect();
        $users = $nonTeaching->merge($specialChiefs);

        $count = 0;

        DB::transaction(function () use ($users, $vlType, $slType, $year, $month, &$count) {
            foreach ($users as $user) {
                foreach ([$vlType, $slType] as $leaveType) {
                    // Idempotency: skip if already accrued this month
                    $accrualRemark = "Monthly accrual — {$year}-{$month}";
                    $alreadyAccrued = LeaveCreditTransaction::where('user_id', $user->id)
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $year)
                        ->where('type', 'ACCRUAL')
                        ->where('remarks', $accrualRemark)
                        ->exists();

                    if ($alreadyAccrued) continue;

                    $credit = $this->ensureCreditRow($user->id, $leaveType->id, $year);
                    $credit->increment('earned', self::MONTHLY_ACCRUAL);
                    $credit = $credit->fresh();

                    LeaveCreditTransaction::create([
                        'user_id'       => $user->id,
                        'leave_type_id' => $leaveType->id,
                        'year'          => $year,
                        'type'          => 'ACCRUAL',
                        'amount'        => self::MONTHLY_ACCRUAL,
                        'balance_after' => (float) $credit->balance,
                        'remarks'       => "Monthly accrual — {$year}-{$month}",
                        'recorded_by'   => null, // system-generated
                    ]);
                }
                $count++;
            }
        });

        Log::info("LeaveCreditService: accrued credits for {$count} employees (non-teaching + special chiefs) ({$year}-{$month}).");

        return $count;
    }

    // ── 4. applyLeaveDeduction ────────────────────────────────────────────────

    /**
     * Deduct leave credits when a leave application is approved.
     *
     * - Teaching staff: deducts from approved ServiceCreditRecords (FIFO by service_date)
     * - Non-Teaching staff: deducts from leave_credits for the matching leave_type_id
     * - Half-day leaves (days_applied = 0.5) are handled naturally
     * - Sets is_without_pay = true when balance is insufficient (AWOL scenario)
     *
     * @throws RuntimeException if the application is not in 'approved' status
     */
    public function applyLeaveDeduction(int $leaveApplicationId, ?int $recordedBy = null): void
    {
        DB::transaction(function () use ($leaveApplicationId, $recordedBy) {
            $app = LeaveApplication::with(['user', 'leaveType'])->lockForUpdate()->findOrFail($leaveApplicationId);

            if ($app->status !== 'approved') {
                throw new RuntimeException("Cannot deduct credits: leave application #{$leaveApplicationId} is not approved.");
            }

            // Prevent double-deduction
            if ($app->days_deducted > 0) {
                return;
            }

            $days = (float) $app->days_applied;
            $year = $app->date_from->year;
            $user = $app->user;

            if ($this->isTeaching($user) && $app->leaveType->code === 'CTO') {
                $this->deductServiceCredits($user->id, $days, $app, $recordedBy);
            } else {
                $this->deductLeaveCredits($user->id, $app->leave_type_id, $days, $year, $app, $recordedBy);
            }
        });
    }

    // ── 5. restoreLeaveCredits ────────────────────────────────────────────────

    /**
     * Restore previously-deducted credits when a leave application is
     * cancelled or rejected after approval.
     *
     * Does nothing if days_deducted is 0 (never deducted).
     */
    public function restoreLeaveCredits(int $leaveApplicationId, ?int $recordedBy = null): void
    {
        DB::transaction(function () use ($leaveApplicationId, $recordedBy) {
            $app = LeaveApplication::with(['user', 'leaveType'])->lockForUpdate()->findOrFail($leaveApplicationId);

            if ((float) $app->days_deducted === 0.0) {
                return; // Nothing was deducted, nothing to restore
            }

            $days = (float) $app->days_deducted;
            $year = $app->date_from->year;
            $user = $app->user;

            if ($this->isTeaching($user) && $app->leaveType->code === 'CTO') {
                $this->restoreServiceCredits($app, $recordedBy);
            } else {
                $credit = $this->ensureCreditRow($user->id, $app->leave_type_id, $year);
                $credit->decrement('used', $days);
                $credit = $credit->fresh();

                LeaveCreditTransaction::create([
                    'user_id'             => $user->id,
                    'leave_type_id'       => $app->leave_type_id,
                    'year'                => $year,
                    'type'                => 'RESTORATION',
                    'amount'              => $days,
                    'balance_after'       => (float) $credit->balance,
                    'referenceable_type'  => LeaveApplication::class,
                    'referenceable_id'    => $app->id,
                    'remarks'             => "Restored: application #{$app->control_no} cancelled/rejected",
                    'recorded_by'         => $recordedBy,
                ]);
            }

            $app->update(['days_deducted' => 0, 'is_without_pay' => false]);
        });
    }

    // ── 6. addServiceCredits ──────────────────────────────────────────────────

    /**
     * Submit a service credit earning record for a Teaching employee.
     * Status starts at 'pending'; use approveServiceCredits() to confirm.
     */
    public function addServiceCredits(
        int    $userId,
        float  $hoursRendered,
        string $serviceType,
        string $serviceDate,
        string $remarks = '',
    ): ServiceCreditRecord {
        $days = ServiceCreditRecord::hoursToDays($hoursRendered);

        if ($days <= 0) {
            throw new RuntimeException("Hours rendered ({$hoursRendered}h) converts to 0 days — minimum is 4 hours.");
        }

        return ServiceCreditRecord::create([
            'user_id'         => $userId,
            'service_date'    => $serviceDate,
            'service_type'    => $serviceType,
            'hours_rendered'  => $hoursRendered,
            'days_equivalent' => $days,
            'status'          => 'pending',
            'expires_at'      => \Carbon\Carbon::parse($serviceDate)->addYear()->toDateString(),
            'remarks'         => $remarks,
        ]);
    }

    // ── 7. approveServiceCredits ──────────────────────────────────────────────

    /**
     * Approve a pending ServiceCreditRecord.
     * Only approved records count toward the employee's CTO balance.
     */
    public function approveServiceCredits(int $recordId, int $approvedBy): ServiceCreditRecord
    {
        $record = ServiceCreditRecord::findOrFail($recordId);

        if ($record->status !== 'pending') {
            throw new RuntimeException("Service credit record #{$recordId} is not pending (current: {$record->status}).");
        }

        $record->update([
            'status'      => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now()->toDateString(),
        ]);

        return $record;
    }

    // ── 8. adjustLeaveCredits ─────────────────────────────────────────────────

    /**
     * Manual positive or negative adjustment of leave credits (HR use only).
     *
     * $type    : 'ADJUSTMENT'
     * $amount  : positive to add credits, negative to remove
     * $remarks : REQUIRED — must state the reason for the adjustment
     */
    public function adjustLeaveCredits(
        int    $userId,
        string $leaveTypeCode,
        float  $amount,
        string $remarks,
        int    $year       = null,
        ?int   $recordedBy = null,
    ): void {
        if (trim($remarks) === '') {
            throw new RuntimeException('Remarks are required for manual credit adjustments.');
        }

        $year ??= now()->year;

        DB::transaction(function () use ($userId, $leaveTypeCode, $amount, $remarks, $year, $recordedBy) {
            $leaveType = LeaveType::where('code', $leaveTypeCode)->firstOrFail();
            $credit    = $this->ensureCreditRow($userId, $leaveType->id, $year);

            if ($amount >= 0) {
                $credit->increment('earned', $amount);
            } else {
                // Treat negative adjustments as additional 'used' to keep balance formula intact
                $credit->increment('used', abs($amount));
            }

            $credit = $credit->fresh();

            LeaveCreditTransaction::create([
                'user_id'       => $userId,
                'leave_type_id' => $leaveType->id,
                'year'          => $year,
                'type'          => 'ADJUSTMENT',
                'amount'        => $amount,
                'balance_after' => (float) $credit->balance,
                'remarks'       => $remarks,
                'recorded_by'   => $recordedBy,
            ]);
        });
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Return (or create) a leave_credits row for the given user/type/year.
     */
    private function ensureCreditRow(int $userId, int $leaveTypeId, int $year): LeaveCredit
    {
        return LeaveCredit::firstOrCreate(
            ['user_id' => $userId, 'leave_type_id' => $leaveTypeId, 'year' => $year],
            ['earned' => 0, 'carried_over' => 0, 'used' => 0, 'forfeited' => 0, 'monetized' => 0],
        );
    }

    /**
     * Deduct from leave_credits for non-teaching (or teaching non-CTO) leaves.
     * Marks is_without_pay if balance is insufficient.
     */
    private function deductLeaveCredits(
        int             $userId,
        int             $leaveTypeId,
        float           $days,
        int             $year,
        LeaveApplication $app,
        ?int            $recordedBy,
    ): void {
        $credit  = $this->ensureCreditRow($userId, $leaveTypeId, $year);
        $balance = (float) $credit->balance;

        $actualDeduct = min($days, max($balance, 0));
        $withoutPay   = $days > $balance;

        if ($actualDeduct > 0) {
            $credit->increment('used', $actualDeduct);
            $credit = $credit->fresh();
        }

        LeaveCreditTransaction::create([
            'user_id'            => $userId,
            'leave_type_id'      => $leaveTypeId,
            'year'               => $year,
            'type'               => 'DEDUCTION',
            'amount'             => -$actualDeduct,
            'balance_after'      => (float) $credit->balance,
            'referenceable_type' => LeaveApplication::class,
            'referenceable_id'   => $app->id,
            'remarks'            => $withoutPay
                ? "Approved leave #{$app->control_no} — {$actualDeduct}d deducted; " . round($days - $actualDeduct, 4) . 'd without pay'
                : "Approved leave #{$app->control_no}",
            'recorded_by'        => $recordedBy,
        ]);

        $app->update([
            'days_deducted' => $actualDeduct,
            'is_without_pay' => $withoutPay,
        ]);
    }

    /**
     * Deduct from ServiceCreditRecords (FIFO by service_date) for Teaching CTO leaves.
     */
    private function deductServiceCredits(
        int             $userId,
        float           $days,
        LeaveApplication $app,
        ?int            $recordedBy,
    ): void {
        $remaining = $days;

        $records = ServiceCreditRecord::active()
            ->where('user_id', $userId)
            ->orderBy('service_date')  // oldest first (FIFO)
            ->lockForUpdate()
            ->get();

        foreach ($records as $record) {
            if ($remaining <= 0) break;

            $consume = min((float) $record->days_equivalent, $remaining);
            $remaining -= $consume;

            if ($consume >= (float) $record->days_equivalent) {
                $record->update([
                    'status'               => 'consumed',
                    'leave_application_id' => $app->id,
                ]);
            } else {
                // Partially consumed — reduce days_equivalent
                $record->decrement('days_equivalent', $consume);
            }
        }

        $withoutPay = $remaining > 0;

        $app->update([
            'days_deducted'  => round($days - $remaining, 4),
            'is_without_pay' => $withoutPay,
        ]);
    }

    /**
     * Re-open consumed ServiceCreditRecords linked to this application.
     */
    private function restoreServiceCredits(LeaveApplication $app, ?int $recordedBy): void
    {
        ServiceCreditRecord::where('leave_application_id', $app->id)
            ->where('status', 'consumed')
            ->each(function (ServiceCreditRecord $record) {
                $record->update(['status' => 'approved', 'leave_application_id' => null]);
            });
    }
}
