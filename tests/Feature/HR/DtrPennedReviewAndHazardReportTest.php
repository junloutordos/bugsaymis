<?php

namespace Tests\Feature\HR;

use App\Models\HR\DtrRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\HR\HazardReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers two related additions:
 * - DtrRecordController::approvePenned()/unlockPenned() — the HR review
 *   actions on employee-submitted "penned" DTR entries.
 * - HazardReportService — Hazard Actual Exposure day classification, the
 *   basis for Hazard Pay. An unreviewed penned day must not count, which is
 *   what makes approvePenned() consequential rather than just a status flag.
 */
class DtrPennedReviewAndHazardReportTest extends TestCase
{
    use RefreshDatabase;

    private function hrManager(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'hr.dtr.manage'],
            ['module' => 'DTR', 'description' => 'Manage DTR'],
        );
        $role = Role::create(['name' => 'HRDtrManager_' . uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    // ── approvePenned() / unlockPenned() ────────────────────────────────────

    public function test_approve_penned_marks_submitted_records_reviewed(): void
    {
        $hr = $this->hrManager();
        $employee = User::factory()->create(['emp_category' => 'Plantilla Teaching']);

        $record = DtrRecord::create([
            'user_id'             => $employee->id,
            'work_date'           => '2026-07-01',
            'hours_worked'        => 8,
            'attendance_status'   => 'present',
            'penned_submitted_at' => now(),
            'penned_submitted_by' => $employee->id,
        ]);

        $this->actingAs($hr)
            ->post(route('hr.dtr.approve-penned', $employee->id), ['month' => '2026-07'])
            ->assertRedirect();

        $record->refresh();
        $this->assertNotNull($record->penned_reviewed_at);
        $this->assertSame($hr->id, $record->penned_reviewed_by);
    }

    public function test_approve_penned_ignores_records_not_submitted(): void
    {
        $hr = $this->hrManager();
        $employee = User::factory()->create(['emp_category' => 'Plantilla Teaching']);

        $record = DtrRecord::create([
            'user_id'           => $employee->id,
            'work_date'         => '2026-07-01',
            'hours_worked'      => 8,
            'attendance_status' => 'present',
        ]);

        $this->actingAs($hr)
            ->post(route('hr.dtr.approve-penned', $employee->id), ['month' => '2026-07']);

        $this->assertNull($record->refresh()->penned_reviewed_at);
    }

    public function test_unlock_penned_clears_any_prior_review(): void
    {
        $hr = $this->hrManager();
        $employee = User::factory()->create(['emp_category' => 'Plantilla Teaching']);

        $record = DtrRecord::create([
            'user_id'             => $employee->id,
            'work_date'           => '2026-07-01',
            'hours_worked'        => 8,
            'attendance_status'   => 'present',
            'penned_submitted_at' => now(),
            'penned_submitted_by' => $employee->id,
            'penned_reviewed_at'  => now(),
            'penned_reviewed_by'  => $hr->id,
        ]);

        $this->actingAs($hr)
            ->post(route('hr.dtr.unlock-penned', $employee->id), ['month' => '2026-07']);

        $record->refresh();
        $this->assertNull($record->penned_submitted_at);
        $this->assertNull($record->penned_reviewed_at);
        $this->assertNull($record->penned_reviewed_by);
    }

    // ── HazardReportService classification ──────────────────────────────────

    private function plantillaUser(): User
    {
        return User::factory()->create([
            'emp_category' => 'Plantilla Non-Teaching',
            'status'       => 'active',
        ]);
    }

    public function test_six_or_more_hours_counts_as_a_full_day(): void
    {
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 6.0, 'attendance_status' => 'present',
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');
        $row = $rows->firstWhere('user_id', $user->id);

        $this->assertSame(1, $row['full_days']);
        $this->assertSame(0, $row['half_days']);
        $this->assertEquals(1.0, $row['total_hazard_days']);
    }

    public function test_four_to_six_hours_counts_as_half_day(): void
    {
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 4.5, 'attendance_status' => 'present',
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');
        $row = $rows->firstWhere('user_id', $user->id);

        $this->assertSame(0, $row['full_days']);
        $this->assertSame(1, $row['half_days']);
        $this->assertEquals(0.5, $row['total_hazard_days']);
    }

    public function test_under_four_hours_is_not_counted(): void
    {
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 3.9, 'attendance_status' => 'present',
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');
        $row = $rows->firstWhere('user_id', $user->id);

        $this->assertEquals(0, $row['total_hazard_days']);
    }

    public function test_wfh_day_never_counts_even_with_full_hours(): void
    {
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 8.0, 'attendance_status' => 'wfh',
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');

        $this->assertEquals(0, $rows->firstWhere('user_id', $user->id)['total_hazard_days']);
    }

    public function test_official_travel_day_never_counts(): void
    {
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 8.0, 'attendance_status' => 'present', 'is_travel' => true,
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');

        $this->assertEquals(0, $rows->firstWhere('user_id', $user->id)['total_hazard_days']);
    }

    public function test_gatepass_minutes_are_subtracted_before_classification(): void
    {
        $user = $this->plantillaUser();
        // 6.0 worked hours minus a 2-hour (120 min) gate-pass deduction = 4.0
        // effective hours — should land as a half day, not a full day.
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 6.0, 'attendance_status' => 'present',
            'gatepass_deduction_minutes' => 120,
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');
        $row = $rows->firstWhere('user_id', $user->id);

        $this->assertSame(0, $row['full_days']);
        $this->assertSame(1, $row['half_days']);
    }

    public function test_unreviewed_penned_day_is_not_counted_even_with_full_hours(): void
    {
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 8.0, 'attendance_status' => 'present',
            'penned_time_in_am' => '08:00:00', 'penned_submitted_at' => now(),
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');

        $this->assertEquals(0, $rows->firstWhere('user_id', $user->id)['total_hazard_days']);
    }

    public function test_unreviewed_penned_lunch_gap_still_counts_when_verified_punches_clear_threshold(): void
    {
        // Real biometric punches: 07:41 in, 17:00 out (no AM-out/PM-in
        // punch — self-declared as a 1-minute lunch gap, unreviewed). The
        // verified span alone (minus the default 60-min lunch deduction)
        // is well past the full-day threshold, so this must still count.
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'attendance_status' => 'present',
            'time_in_am' => '07:41:00', 'time_out_pm' => '17:00:00',
            'hours_worked' => 9.30,
            'penned_time_out_am' => '12:01:00', 'penned_time_in_pm' => '12:02:00',
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');

        $this->assertEquals(1.0, $rows->firstWhere('user_id', $user->id)['total_hazard_days']);
    }

    public function test_unreviewed_penned_day_stays_excluded_when_verified_punches_dont_clear_threshold(): void
    {
        // Only a 2-hour verified AM session; the rest of the day is
        // self-declared and unreviewed — verified-only hours (2h) don't
        // clear even the half-day threshold, so it must stay excluded.
        $user = $this->plantillaUser();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'attendance_status' => 'present',
            'time_in_am' => '07:41:00', 'time_out_am' => '09:41:00',
            'hours_worked' => 8.0,
            'penned_time_in_pm' => '13:00:00', 'penned_time_out_pm' => '17:00:00',
            'penned_submitted_at' => now(),
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');

        $this->assertEquals(0, $rows->firstWhere('user_id', $user->id)['total_hazard_days']);
    }

    public function test_hr_reviewed_penned_day_counts_normally(): void
    {
        $user = $this->plantillaUser();
        $hr = $this->hrManager();
        DtrRecord::create([
            'user_id' => $user->id, 'work_date' => '2026-07-01',
            'hours_worked' => 8.0, 'attendance_status' => 'present',
            'penned_time_in_am' => '08:00:00', 'penned_submitted_at' => now(),
            'penned_reviewed_at' => now(), 'penned_reviewed_by' => $hr->id,
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');

        $this->assertEquals(1.0, $rows->firstWhere('user_id', $user->id)['total_hazard_days']);
    }

    public function test_non_plantilla_employees_are_excluded_from_the_roster(): void
    {
        $cos = User::factory()->create(['emp_category' => 'COS Non Teaching', 'status' => 'active']);
        DtrRecord::create([
            'user_id' => $cos->id, 'work_date' => '2026-07-01',
            'hours_worked' => 8.0, 'attendance_status' => 'present',
        ]);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');

        $this->assertNull($rows->firstWhere('user_id', $cos->id));
    }

    public function test_roster_is_sorted_alphabetically_by_name(): void
    {
        User::factory()->create(['name' => 'Zamora, Anna', 'emp_category' => 'Plantilla Teaching', 'status' => 'active']);
        User::factory()->create(['name' => 'Alonzo, Ben', 'emp_category' => 'Plantilla Non-Teaching', 'status' => 'active']);

        $rows = app(HazardReportService::class)->generate('2026-07-01', '2026-07-31');
        $names = $rows->pluck('name')->values()->all();

        $this->assertSame($names, collect($names)->sort()->values()->all());
    }
}
