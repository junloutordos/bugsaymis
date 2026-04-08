<?php

namespace Tests\Unit;

use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveCreditTransaction;
use App\Models\HR\LeaveType;
use App\Models\HR\ServiceCreditRecord;
use App\Models\User;
use App\Services\HR\LeaveCreditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for LeaveCreditService.
 *
 * Covers:
 *  - initializeLeaveCredits (normal, duplicate, force)
 *  - accrueMonthlyCredits (non-teaching only, idempotency)
 *  - applyLeaveDeduction (full balance, partial/LWOP, half-day, double-deduction guard)
 *  - restoreLeaveCredits (after approval, no-op when nothing deducted)
 *  - adjustLeaveCredits (positive, negative, remarks required)
 *  - addServiceCredits / approveServiceCredits
 *  - Teaching vs Non-Teaching mismatch
 *  - Zero balance edge case
 */
class LeaveCreditServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LeaveCreditService $service;
    private LeaveType $vl;
    private LeaveType $sl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LeaveCreditService::class);

        $this->vl = LeaveType::firstOrCreate(
            ['code' => 'VL'],
            ['name' => 'Vacation Leave', 'is_deductible' => true, 'is_creditable' => true, 'is_active' => true, 'sort_order' => 1]
        );

        $this->sl = LeaveType::firstOrCreate(
            ['code' => 'SL'],
            ['name' => 'Sick Leave', 'is_deductible' => true, 'is_creditable' => true, 'is_active' => true, 'sort_order' => 2]
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function nonTeachingUser(): User
    {
        return User::factory()->create(['emp_category' => 'Plantilla Non-Teaching']);
    }

    private function teachingUser(): User
    {
        return User::factory()->create(['emp_category' => 'Plantilla Teaching']);
    }

    private function makeApprovedApplication(User $user, LeaveType $type, float $days, int $year = null): LeaveApplication
    {
        $year ??= now()->year;
        $from  = now()->setYear($year)->startOfYear()->addMonths(2)->toDateString();
        $to    = now()->setYear($year)->startOfYear()->addMonths(2)->addDays((int) $days - 1)->toDateString();

        return LeaveApplication::create([
            'user_id'       => $user->id,
            'leave_type_id' => $type->id,
            'date_from'     => $from,
            'date_to'       => $to,
            'days_applied'  => $days,
            'days_deducted' => 0,
            'is_without_pay' => false,
            'status'        => 'approved',
        ]);
    }

    // ── initializeLeaveCredits ────────────────────────────────────────────────

    public function test_initialize_creates_credit_row_and_initial_transaction(): void
    {
        $user = $this->nonTeachingUser();

        $this->service->initializeLeaveCredits(
            userId:  $user->id,
            values:  ['VL' => 25.5, 'SL' => 18.75],
            remarks: 'Migration from HR records',
            year:    2026,
        );

        $vlCredit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($vlCredit);
        $this->assertEquals(25.5, (float) $vlCredit->carried_over);
        $this->assertEquals(25.5, (float) $vlCredit->balance);

        $this->assertDatabaseHas('leave_credit_transactions', [
            'user_id'       => $user->id,
            'leave_type_id' => $this->vl->id,
            'year'          => 2026,
            'type'          => 'INITIAL',
            'amount'        => 25.5,
        ]);

        $this->assertDatabaseHas('leave_credit_transactions', [
            'user_id'       => $user->id,
            'leave_type_id' => $this->sl->id,
            'year'          => 2026,
            'type'          => 'INITIAL',
            'amount'        => 18.75,
        ]);
    }

    public function test_initialize_throws_on_duplicate_without_force(): void
    {
        $user = $this->nonTeachingUser();

        $this->service->initializeLeaveCredits($user->id, ['VL' => 10], 'First', 2026);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already exist/i');

        $this->service->initializeLeaveCredits($user->id, ['VL' => 20], 'Second', 2026);
    }

    public function test_initialize_with_force_overrides_existing_balance(): void
    {
        $user = $this->nonTeachingUser();

        $this->service->initializeLeaveCredits($user->id, ['VL' => 10], 'First', 2026);
        $this->service->initializeLeaveCredits($user->id, ['VL' => 30], 'Override', 2026, force: true);

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(30.0, (float) $credit->carried_over);
    }

    public function test_initialize_throws_for_unknown_leave_type(): void
    {
        $user = $this->nonTeachingUser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unknown leave type/i');

        $this->service->initializeLeaveCredits($user->id, ['BOGUS' => 5], 'Test', 2026);
    }

    // ── accrueMonthlyCredits ──────────────────────────────────────────────────

    public function test_accrue_adds_1_25_vl_and_sl_for_non_teaching(): void
    {
        $user = $this->nonTeachingUser();

        $count = $this->service->accrueMonthlyCredits(2026, 3);

        $this->assertGreaterThanOrEqual(1, $count);

        $this->assertDatabaseHas('leave_credits', [
            'user_id'       => $user->id,
            'leave_type_id' => $this->vl->id,
            'year'          => 2026,
            'earned'        => 1.25,
        ]);
    }

    public function test_accrue_skips_teaching_personnel(): void
    {
        $teaching    = $this->teachingUser();
        $nonTeaching = $this->nonTeachingUser();

        $this->service->accrueMonthlyCredits(2026, 3);

        // Teaching user should have NO VL/SL credit rows created by accrual
        $this->assertDatabaseMissing('leave_credits', [
            'user_id'       => $teaching->id,
            'leave_type_id' => $this->vl->id,
        ]);

        // Non-teaching user should have credit row
        $this->assertDatabaseHas('leave_credits', [
            'user_id'       => $nonTeaching->id,
            'leave_type_id' => $this->vl->id,
        ]);
    }

    public function test_accrue_is_idempotent_for_same_month(): void
    {
        $user = $this->nonTeachingUser();

        $this->service->accrueMonthlyCredits(2026, 3);
        $this->service->accrueMonthlyCredits(2026, 3); // re-run same month

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        // Must still be 1.25, not 2.50
        $this->assertEquals(1.25, (float) $credit->earned);
    }

    // ── applyLeaveDeduction ───────────────────────────────────────────────────

    public function test_deduction_reduces_used_and_logs_transaction(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 15.0], 'Init', 2026);

        $app = $this->makeApprovedApplication($user, $this->vl, 5.0);

        $this->service->applyLeaveDeduction($app->id);

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(5.0, (float) $credit->used);
        $this->assertEquals(10.0, (float) $credit->balance);

        $this->assertDatabaseHas('leave_credit_transactions', [
            'user_id'       => $user->id,
            'leave_type_id' => $this->vl->id,
            'type'          => 'DEDUCTION',
        ]);

        $app->refresh();
        $this->assertEquals(5.0, (float) $app->days_deducted);
        $this->assertFalse((bool) $app->is_without_pay);
    }

    public function test_deduction_sets_lwop_when_balance_insufficient(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 2.0], 'Init', 2026);

        $app = $this->makeApprovedApplication($user, $this->vl, 5.0);

        $this->service->applyLeaveDeduction($app->id);

        $app->refresh();
        $this->assertEquals(2.0, (float) $app->days_deducted);
        $this->assertTrue((bool) $app->is_without_pay);
    }

    public function test_deduction_handles_zero_balance_sets_full_lwop(): void
    {
        $user = $this->nonTeachingUser();
        // No initialization — zero balance

        $app = $this->makeApprovedApplication($user, $this->vl, 3.0);

        $this->service->applyLeaveDeduction($app->id);

        $app->refresh();
        $this->assertEquals(0.0, (float) $app->days_deducted);
        $this->assertTrue((bool) $app->is_without_pay);
    }

    public function test_half_day_deduction_works_correctly(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 10.0], 'Init', 2026);

        $app = $this->makeApprovedApplication($user, $this->vl, 0.5);

        $this->service->applyLeaveDeduction($app->id);

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(0.5, (float) $credit->used);
        $this->assertEquals(9.5, (float) $credit->balance);
        $this->assertFalse((bool) $app->fresh()->is_without_pay);
    }

    public function test_double_deduction_is_prevented(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 10.0], 'Init', 2026);

        $app = $this->makeApprovedApplication($user, $this->vl, 3.0);

        $this->service->applyLeaveDeduction($app->id);
        $this->service->applyLeaveDeduction($app->id); // second call — must be no-op

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(3.0, (float) $credit->used); // still 3, not 6
    }

    public function test_deduction_throws_when_application_not_approved(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 10.0], 'Init', 2026);

        $app = $this->makeApprovedApplication($user, $this->vl, 3.0);
        $app->update(['status' => 'pending']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not approved/i');

        $this->service->applyLeaveDeduction($app->id);
    }

    // ── restoreLeaveCredits ───────────────────────────────────────────────────

    public function test_restore_reverses_deduction_and_logs_restoration(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 10.0], 'Init', 2026);

        $app = $this->makeApprovedApplication($user, $this->vl, 4.0);
        $this->service->applyLeaveDeduction($app->id);

        $this->service->restoreLeaveCredits($app->id);

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(0.0, (float) $credit->used);
        $this->assertEquals(10.0, (float) $credit->balance);

        $this->assertDatabaseHas('leave_credit_transactions', [
            'user_id' => $user->id,
            'type'    => 'RESTORATION',
        ]);

        $app->refresh();
        $this->assertEquals(0.0, (float) $app->days_deducted);
    }

    public function test_restore_is_noop_when_nothing_was_deducted(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 10.0], 'Init', 2026);

        $app = $this->makeApprovedApplication($user, $this->vl, 3.0);
        // No deduction applied

        $this->service->restoreLeaveCredits($app->id);

        $txCount = LeaveCreditTransaction::where('user_id', $user->id)
            ->where('type', 'RESTORATION')
            ->count();

        $this->assertEquals(0, $txCount);
    }

    // ── adjustLeaveCredits ────────────────────────────────────────────────────

    public function test_positive_adjustment_increases_balance(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 5.0], 'Init', 2026);

        $this->service->adjustLeaveCredits($user->id, 'VL', 3.0, 'Correction by HR', 2026);

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(8.0, (float) $credit->balance);

        $this->assertDatabaseHas('leave_credit_transactions', [
            'user_id' => $user->id,
            'type'    => 'ADJUSTMENT',
            'amount'  => 3.0,
        ]);
    }

    public function test_negative_adjustment_decreases_balance(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 10.0], 'Init', 2026);

        $this->service->adjustLeaveCredits($user->id, 'VL', -2.0, 'Overpayment correction', 2026);

        $credit = LeaveCredit::where('user_id', $user->id)
            ->where('leave_type_id', $this->vl->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(8.0, (float) $credit->balance);
    }

    public function test_adjustment_requires_remarks(): void
    {
        $user = $this->nonTeachingUser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/remarks are required/i');

        $this->service->adjustLeaveCredits($user->id, 'VL', 5.0, '   ', 2026);
    }

    // ── Teaching vs Non-Teaching ──────────────────────────────────────────────

    public function test_is_teaching_returns_correct_classification(): void
    {
        $teaching    = $this->teachingUser();
        $nonTeaching = $this->nonTeachingUser();
        $cosTeaching = User::factory()->create(['emp_category' => 'COS Teaching']);
        $cosNT       = User::factory()->create(['emp_category' => 'COS Non Teaching']);

        $this->assertTrue($this->service->isTeaching($teaching));
        $this->assertTrue($this->service->isTeaching($cosTeaching));
        $this->assertFalse($this->service->isTeaching($nonTeaching));
        $this->assertFalse($this->service->isTeaching($cosNT));
    }

    public function test_teaching_user_excluded_from_vl_sl_accrual(): void
    {
        $teaching = $this->teachingUser();

        $this->service->accrueMonthlyCredits(2026, 3);

        $this->assertEquals(
            0,
            LeaveCredit::where('user_id', $teaching->id)->count(),
            'Teaching personnel must not receive VL/SL accrual'
        );
    }

    // ── Service Credits (Teaching) ────────────────────────────────────────────

    public function test_add_service_credits_converts_hours_to_days(): void
    {
        $user = $this->teachingUser();

        $record = $this->service->addServiceCredits(
            userId:       $user->id,
            hoursRendered: 8.0,
            serviceType:  'school_activity',
            serviceDate:  '2026-03-15',
            remarks:      'Graduation ceremony',
        );

        $this->assertEquals('pending', $record->status);
        $this->assertEquals(1.0, (float) $record->days_equivalent); // 8h / 8 = 1.0d
    }

    public function test_partial_hours_round_down_to_nearest_half_day(): void
    {
        // 6 hours = 0.75 → floor to 0.5
        $this->assertEquals(0.5, ServiceCreditRecord::hoursToDays(6));

        // 4 hours = 0.5 → floor to 0.5
        $this->assertEquals(0.5, ServiceCreditRecord::hoursToDays(4));

        // 12 hours = 1.5 → floor to 1.5
        $this->assertEquals(1.5, ServiceCreditRecord::hoursToDays(12));

        // 3 hours = 0.375 → floor to 0 (minimum 4 hours)
        $this->assertEquals(0.0, ServiceCreditRecord::hoursToDays(3));
    }

    public function test_add_service_credits_throws_when_hours_too_low(): void
    {
        $user = $this->teachingUser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/converts to 0 days/i');

        $this->service->addServiceCredits($user->id, 3.0, 'committee_work', '2026-03-15');
    }

    public function test_approve_service_credits_marks_record_approved(): void
    {
        $user   = $this->teachingUser();
        $hr     = $this->nonTeachingUser();
        $record = $this->service->addServiceCredits($user->id, 8.0, 'school_activity', '2026-03-15');

        $this->service->approveServiceCredits($record->id, $hr->id);

        $record->refresh();
        $this->assertEquals('approved', $record->status);
        $this->assertEquals($hr->id, $record->approved_by);
    }

    public function test_approve_throws_when_record_not_pending(): void
    {
        $user   = $this->teachingUser();
        $hr     = $this->nonTeachingUser();
        $record = $this->service->addServiceCredits($user->id, 8.0, 'school_activity', '2026-03-15');
        $this->service->approveServiceCredits($record->id, $hr->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not pending/i');

        $this->service->approveServiceCredits($record->id, $hr->id);
    }

    public function test_teaching_cto_balance_aggregates_active_service_credits(): void
    {
        $user = $this->teachingUser();
        $hr   = $this->nonTeachingUser();

        $r1 = $this->service->addServiceCredits($user->id, 8.0, 'school_activity', '2026-03-01');
        $r2 = $this->service->addServiceCredits($user->id, 16.0, 'committee_work', '2026-03-10');

        $this->service->approveServiceCredits($r1->id, $hr->id);
        $this->service->approveServiceCredits($r2->id, $hr->id);

        $balances = $this->service->getEmployeeLeaveBalance($user->id, 2026);

        $this->assertArrayHasKey('CTO', $balances);
        $this->assertEquals(3.0, $balances['CTO']['balance']); // 1.0 + 2.0
    }

    // ── getEmployeeLeaveBalance ───────────────────────────────────────────────

    public function test_get_balance_returns_keyed_array_by_leave_type_code(): void
    {
        $user = $this->nonTeachingUser();
        $this->service->initializeLeaveCredits($user->id, ['VL' => 20.0, 'SL' => 15.0], 'Init', 2026);

        $balances = $this->service->getEmployeeLeaveBalance($user->id, 2026);

        $this->assertArrayHasKey('VL', $balances);
        $this->assertArrayHasKey('SL', $balances);
        $this->assertEquals(20.0, $balances['VL']['balance']);
        $this->assertEquals(15.0, $balances['SL']['balance']);
    }

    public function test_get_balance_returns_empty_for_year_with_no_records(): void
    {
        $user     = $this->nonTeachingUser();
        $balances = $this->service->getEmployeeLeaveBalance($user->id, 1999);

        $this->assertEmpty(array_filter($balances, fn ($b) => !isset($b['is_service_credit'])));
    }
}
