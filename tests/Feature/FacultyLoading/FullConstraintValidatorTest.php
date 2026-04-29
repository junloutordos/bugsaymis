<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\ConflictDetectionService;
use App\Services\FacultyLoading\FullConstraintValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for FullConstraintValidator — Phase 9.
 *
 * Constant-constraint tests (H4–H12): pure-logic, no DB.
 * DB-constraint tests (H1, H2, H3, H14): use DatabaseTransactions with
 *   raw DB inserts (FK checks disabled inside the transaction) so no
 *   factory chains are needed.
 */
class FullConstraintValidatorTest extends TestCase
{
    use DatabaseTransactions;

    private FullConstraintValidator $validator;

    /** Dummy ID guaranteed not to exist in the DB (avoids FK conflicts) */
    private const DUMMY_ID = 88888;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FullConstraintValidator(new ConflictDetectionService());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Disable FK checks, run $callback, re-enable. */
    private function withoutFKChecks(callable $callback): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $callback();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** Insert a teacher_official_times row with a dummy user ID. */
    private function insertOfficialTime(string $day, string $start, string $end, int $userId = self::DUMMY_ID): void
    {
        $this->withoutFKChecks(function () use ($day, $start, $end, $userId) {
            DB::table('teacher_official_times')->insert([
                'user_id'     => $userId,
                'day_of_week' => $day,
                'start_time'  => $start . ':00',
                'end_time'    => $end   . ':00',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        });
    }

    /** Insert a class_schedules row (all required columns, dummy IDs). */
    private function insertSchedule(array $attrs): void
    {
        $this->withoutFKChecks(function () use ($attrs) {
            DB::table('class_schedules')->insert(array_merge([
                'user_id'          => self::DUMMY_ID,
                'subject_id'       => self::DUMMY_ID,
                'section_id'       => self::DUMMY_ID,
                'classroom_id'     => self::DUMMY_ID,
                'school_year_id'   => self::DUMMY_ID,
                'academic_term_id' => self::DUMMY_ID,
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ], $attrs));
        });
    }

    // =========================================================================
    // checkConstant — result shape
    // =========================================================================

    public function test_check_constant_returns_passes_and_violations_keys(): void
    {
        $result = $this->validator->checkConstant(7, 'Tuesday', '07:30', '08:20');
        $this->assertArrayHasKey('passes',     $result);
        $this->assertArrayHasKey('violations', $result);
    }

    public function test_check_constant_passes_for_valid_g7_tuesday_slot(): void
    {
        $result = $this->validator->checkConstant(7, 'Tuesday', '07:30', '08:20');
        $this->assertTrue($result['passes']);
        $this->assertEmpty($result['violations']);
    }

    public function test_check_constant_passes_for_valid_g9_friday_slot(): void
    {
        $result = $this->validator->checkConstant(9, 'Friday', '07:30', '08:20');
        $this->assertTrue($result['passes']);
        $this->assertEmpty($result['violations']);
    }

    // =========================================================================
    // checkConstant — H9 (Monday dead zone G7/G8)
    // =========================================================================

    public function test_check_constant_fails_h9_for_g7_monday_dead_zone(): void
    {
        // H9 fires from both checkSpecialDayLocks AND checkFixedSlotOverlap (DEAD slot)
        $result = $this->validator->checkConstant(7, 'Monday', '08:50', '09:40');
        $this->assertFalse($result['passes']);
        $this->assertNotEmpty($result['violations']);
        $codes = array_column($result['violations'], 'code');
        $this->assertContains('H9', $codes);
        foreach ($result['violations'] as $v) {
            $this->assertStringContainsString('H9', $v['reason']);
        }
    }

    public function test_check_constant_passes_h9_for_g9_monday_same_window(): void
    {
        // G9 has Period 1 at 08:50–09:40 — no dead zone
        $result = $this->validator->checkConstant(9, 'Monday', '08:50', '09:40');
        $this->assertTrue($result['passes']);
    }

    // =========================================================================
    // checkConstant — H10 (Wednesday Activity lock)
    // =========================================================================

    public function test_check_constant_fails_h10_for_g7_wednesday_after_lock(): void
    {
        $result = $this->validator->checkConstant(7, 'Wednesday', '15:00', '15:50');
        $this->assertFalse($result['passes']);
        $this->assertSame('H10', $result['violations'][0]['code']);
    }

    public function test_check_constant_fails_h10_for_g11_wednesday_after_12_20(): void
    {
        $result = $this->validator->checkConstant(11, 'Wednesday', '12:20', '13:10');
        $this->assertFalse($result['passes']);
        $this->assertSame('H10', $result['violations'][0]['code']);
    }

    public function test_check_constant_passes_h10_for_g7_wednesday_before_15(): void
    {
        $result = $this->validator->checkConstant(7, 'Wednesday', '14:00', '14:50');
        $this->assertTrue($result['passes']);
    }

    // =========================================================================
    // checkConstant — H11 (Wednesday Wellness)
    // =========================================================================

    public function test_check_constant_fails_h11_for_wednesday_wellness_overlap(): void
    {
        $result = $this->validator->checkConstant(9, 'Wednesday', '09:50', '10:40');
        $this->assertFalse($result['passes']);
        $this->assertSame('H11', $result['violations'][0]['code']);
    }

    public function test_check_constant_passes_h11_for_wednesday_slot_after_wellness(): void
    {
        $result = $this->validator->checkConstant(9, 'Wednesday', '10:20', '11:10');
        $this->assertTrue($result['passes']);
    }

    // =========================================================================
    // checkConstant — H12 (Friday ILA)
    // =========================================================================

    public function test_check_constant_fails_h12_for_g7_friday(): void
    {
        $result = $this->validator->checkConstant(7, 'Friday', '07:30', '08:20');
        $this->assertFalse($result['passes']);
        $this->assertSame('H12', $result['violations'][0]['code']);
    }

    public function test_check_constant_passes_h12_for_g9_friday(): void
    {
        $result = $this->validator->checkConstant(9, 'Friday', '07:30', '08:20');
        $this->assertTrue($result['passes']);
    }

    // =========================================================================
    // checkConstant — H4–H8 (fixed slots)
    // =========================================================================

    public function test_check_constant_fails_fixed_for_g7_tuesday_lunch(): void
    {
        // G7 Tue–Fri lunch: 10:20–11:20 — must not schedule classes here
        $result = $this->validator->checkConstant(7, 'Tuesday', '10:20', '11:20');
        $this->assertFalse($result['passes']);
        $this->assertStringContainsString('H', $result['violations'][0]['code']);
    }

    public function test_check_constant_fails_fixed_for_g9_monday_flag(): void
    {
        // Monday 07:30–08:00 is FLAG for all grades
        $result = $this->validator->checkConstant(9, 'Monday', '07:30', '08:00');
        $this->assertFalse($result['passes']);
        $this->assertSame('H4', $result['violations'][0]['code']);
    }

    public function test_check_constant_fails_fixed_for_g7_monday_homeroom(): void
    {
        // G7 Monday 08:00–08:50 is HOMEROOM
        $result = $this->validator->checkConstant(7, 'Monday', '08:00', '08:50');
        $this->assertFalse($result['passes']);
        $this->assertSame('H5', $result['violations'][0]['code']);
    }

    // =========================================================================
    // checkConstant — violations have correct shape
    // =========================================================================

    public function test_violations_have_code_and_reason_keys(): void
    {
        $result = $this->validator->checkConstant(7, 'Friday', '07:30', '08:20');
        $this->assertNotEmpty($result['violations']);

        foreach ($result['violations'] as $v) {
            $this->assertArrayHasKey('code',   $v);
            $this->assertArrayHasKey('reason', $v);
            $this->assertIsString($v['code']);
            $this->assertIsString($v['reason']);
            $this->assertNotEmpty($v['code']);
            $this->assertNotEmpty($v['reason']);
        }
    }

    public function test_constant_can_collect_two_violations_when_both_fire(): void
    {
        // G7 Wednesday: wellness AND fixed slot can both fire for the same window.
        // Choose a slot that hits H11 (wellness 09:50–10:20) and also overlaps CONSULT — not easy.
        // Instead: use a slot that would be both a dead-zone overlap AND homeroom.
        // Easier: G7 Friday overlaps HOMEROOM: Friday HOMEROOM slot.
        // Actually for G7 Friday, H12 fires (constant) — let's check if the fixed slot also fires.
        // Since Friday for G7 uses the TueFri timetable: 08:00–08:50 is CLASS (not homeroom).
        // So H12 fires but H4–H8 won't fire for a valid class slot time.
        //
        // Let's use a Wednesday slot that overlaps both wellness AND a non-class slot:
        // G9 Wednesday 09:50–10:20 → H11 fires. Is it also a fixed slot? Wellness itself
        // is not in the timetable as a fixed slot for G9/G10 Wednesday. So only H11 fires.
        //
        // Simplest two-violation case: H9 (Monday dead zone) could overlap with a fixed slot?
        // G7 Monday 08:50–09:40 → H9 fires. Is 08:50–09:40 a DEAD slot (type DEAD)?
        // In MONDAY_G7G8: ['start' => '08:50', 'end' => '09:40', 'type' => 'DEAD'] → yes!
        // So checkFixedSlotOverlap would ALSO fire for H9 (DEAD → code '9').
        // checkSpecialDayLocks fires H9 first.
        // Both checks run in checkConstant → 2 violations.
        $result = $this->validator->checkConstant(7, 'Monday', '08:50', '09:40');
        $this->assertFalse($result['passes']);
        $this->assertCount(2, $result['violations'],
            'G7 Monday 08:50–09:40 should produce H9 (special lock) + H9 (fixed DEAD slot) = 2 violations'
        );
    }

    // =========================================================================
    // checkH3 — teacher official time (no DB record → default early shift)
    // =========================================================================

    public function test_h3_passes_when_class_within_default_early_shift(): void
    {
        // DUMMY_ID has no OT record → falls back to early shift 07:30–16:30
        $violations = $this->validator->checkH3(self::DUMMY_ID, 'Tuesday', '08:00', '08:50');
        $this->assertEmpty($violations);
    }

    public function test_h3_fails_when_class_starts_before_default_early_shift(): void
    {
        // 07:00 < 07:30 (SHIFT_EARLY start)
        $violations = $this->validator->checkH3(self::DUMMY_ID, 'Tuesday', '07:00', '07:30');
        $this->assertCount(1, $violations);
        $this->assertSame('H3', $violations[0]['code']);
        $this->assertStringContainsString('H3', $violations[0]['reason']);
    }

    public function test_h3_fails_when_class_ends_after_default_early_shift(): void
    {
        // 17:00 > 16:30 (SHIFT_EARLY end)
        $violations = $this->validator->checkH3(self::DUMMY_ID, 'Tuesday', '16:00', '17:00');
        $this->assertCount(1, $violations);
        $this->assertSame('H3', $violations[0]['code']);
    }

    public function test_h3_passes_when_class_exactly_at_shift_boundaries(): void
    {
        // Exactly 07:30–16:30 — should pass (start >= otStart AND end <= otEnd)
        $violations = $this->validator->checkH3(self::DUMMY_ID, 'Monday', '07:30', '16:30');
        $this->assertEmpty($violations);
    }

    // =========================================================================
    // checkH3 — with a DB official time record
    // =========================================================================

    public function test_h3_passes_when_class_within_recorded_late_shift(): void
    {
        $this->insertOfficialTime('Tuesday', '08:00', '17:00');

        $violations = $this->validator->checkH3(self::DUMMY_ID, 'Tuesday', '08:00', '09:00');
        $this->assertEmpty($violations);
    }

    public function test_h3_fails_when_class_before_recorded_late_shift_start(): void
    {
        // Teacher has late shift 08:00–17:00; class at 07:30 → before shift
        $this->insertOfficialTime('Tuesday', '08:00', '17:00');

        $violations = $this->validator->checkH3(self::DUMMY_ID, 'Tuesday', '07:30', '08:20');
        $this->assertCount(1, $violations);
        $this->assertSame('H3', $violations[0]['code']);
    }

    public function test_h3_uses_different_day_record_independently(): void
    {
        // Record for Tuesday only — Monday has no record → falls back to default shift
        $this->insertOfficialTime('Tuesday', '08:00', '17:00', self::DUMMY_ID);

        // Monday: no record → default early shift → 07:30 start is fine
        $mondayViolations = $this->validator->checkH3(self::DUMMY_ID, 'Monday', '07:30', '08:20');
        $this->assertEmpty($mondayViolations);

        // Tuesday: has late-shift record → 07:30 before 08:00 fails
        $tuesdayViolations = $this->validator->checkH3(self::DUMMY_ID, 'Tuesday', '07:30', '08:20');
        $this->assertCount(1, $tuesdayViolations);
    }

    // =========================================================================
    // checkH1 — teacher conflict (DB)
    // =========================================================================

    public function test_h1_returns_no_violations_when_no_conflict_in_db(): void
    {
        $violations = $this->validator->checkH1(
            self::DUMMY_ID, 'Tuesday', '07:30', '08:20',
            self::DUMMY_ID  // termId
        );
        $this->assertEmpty($violations);
    }

    public function test_h1_fires_when_teacher_has_conflicting_active_schedule(): void
    {
        $this->insertSchedule([
            'user_id'          => self::DUMMY_ID,
            'academic_term_id' => self::DUMMY_ID,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30:00',
            'end_time'         => '08:20:00',
            'status'           => 'active',
        ]);

        $violations = $this->validator->checkH1(
            self::DUMMY_ID, 'Tuesday', '07:30', '08:20',
            self::DUMMY_ID
        );

        $this->assertCount(1, $violations);
        $this->assertSame('H1', $violations[0]['code']);
        $this->assertStringContainsString('H1', $violations[0]['reason']);
    }

    public function test_h1_does_not_fire_for_adjacent_non_overlapping_slots(): void
    {
        $this->insertSchedule([
            'user_id'          => self::DUMMY_ID,
            'academic_term_id' => self::DUMMY_ID,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30:00',
            'end_time'         => '08:20:00',
            'status'           => 'active',
        ]);

        // Adjacent slot (starts at 08:20 — touching boundary, not overlapping)
        $violations = $this->validator->checkH1(
            self::DUMMY_ID, 'Tuesday', '08:20', '09:10',
            self::DUMMY_ID
        );

        $this->assertEmpty($violations, 'Adjacent slots should not conflict (touching boundary)');
    }

    // =========================================================================
    // checkH2 — section conflict (DB)
    // =========================================================================

    public function test_h2_returns_no_violations_when_no_conflict(): void
    {
        $violations = $this->validator->checkH2(
            self::DUMMY_ID, 'Monday', '10:00', '10:50',
            self::DUMMY_ID
        );
        $this->assertEmpty($violations);
    }

    public function test_h2_fires_when_section_has_overlapping_schedule(): void
    {
        $this->insertSchedule([
            'section_id'       => self::DUMMY_ID,
            'academic_term_id' => self::DUMMY_ID,
            'day_of_week'      => 'Monday',
            'start_time'       => '10:00:00',
            'end_time'         => '10:50:00',
            'status'           => 'active',
        ]);

        $violations = $this->validator->checkH2(
            self::DUMMY_ID, 'Monday', '10:00', '10:50',
            self::DUMMY_ID
        );

        $this->assertCount(1, $violations);
        $this->assertSame('H2', $violations[0]['code']);
    }

    // =========================================================================
    // checkH14 — room conflict (DB)
    // =========================================================================

    public function test_h14_returns_no_violations_when_room_is_free(): void
    {
        $violations = $this->validator->checkH14(
            self::DUMMY_ID, 'Thursday', '09:30', '10:20',
            self::DUMMY_ID
        );
        $this->assertEmpty($violations);
    }

    public function test_h14_fires_when_room_has_overlapping_schedule(): void
    {
        $this->insertSchedule([
            'classroom_id'     => self::DUMMY_ID,
            'academic_term_id' => self::DUMMY_ID,
            'day_of_week'      => 'Thursday',
            'start_time'       => '09:30:00',
            'end_time'         => '10:20:00',
            'status'           => 'active',
        ]);

        $violations = $this->validator->checkH14(
            self::DUMMY_ID, 'Thursday', '09:30', '10:20',
            self::DUMMY_ID
        );

        $this->assertCount(1, $violations);
        $this->assertSame('H14', $violations[0]['code']);
    }

    // =========================================================================
    // checkDB — combined DB result shape
    // =========================================================================

    public function test_check_db_returns_passes_and_violations_keys(): void
    {
        $result = $this->validator->checkDB([
            'grade'            => 7,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
            'academic_term_id' => self::DUMMY_ID,
            'faculty_id'       => self::DUMMY_ID,
            'section_id'       => self::DUMMY_ID,
            'classroom_id'     => self::DUMMY_ID,
        ]);

        $this->assertArrayHasKey('passes',     $result);
        $this->assertArrayHasKey('violations', $result);
    }

    public function test_check_db_passes_when_all_ids_are_absent(): void
    {
        // No IDs → no DB checks run → should pass
        $result = $this->validator->checkDB([
            'grade'       => 7,
            'day_of_week' => 'Tuesday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        $this->assertTrue($result['passes']);
        $this->assertEmpty($result['violations']);
    }

    public function test_check_db_collects_h1_and_h2_violations_independently(): void
    {
        // Insert conflict for both teacher and section at same slot
        $this->insertSchedule([
            'user_id'          => self::DUMMY_ID,
            'section_id'       => self::DUMMY_ID,
            'academic_term_id' => self::DUMMY_ID,
            'day_of_week'      => 'Wednesday',
            'start_time'       => '08:20:00',
            'end_time'         => '09:10:00',
            'status'           => 'active',
        ]);

        $result = $this->validator->checkDB([
            'grade'            => 7,
            'day_of_week'      => 'Wednesday',
            'start_time'       => '08:20',
            'end_time'         => '09:10',
            'academic_term_id' => self::DUMMY_ID,
            'faculty_id'       => self::DUMMY_ID,   // H1 check
            'section_id'       => self::DUMMY_ID,   // H2 check
        ]);

        $this->assertFalse($result['passes']);
        $codes = array_column($result['violations'], 'code');
        $this->assertContains('H1', $codes);
        $this->assertContains('H2', $codes);
    }

    // =========================================================================
    // check — full integration
    // =========================================================================

    public function test_check_returns_passes_and_violations_keys(): void
    {
        $result = $this->validator->check([
            'grade'       => 7,
            'day_of_week' => 'Tuesday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        $this->assertArrayHasKey('passes',     $result);
        $this->assertArrayHasKey('violations', $result);
    }

    public function test_check_passes_for_clean_valid_slot_no_ids(): void
    {
        // G9 Tuesday 07:30–08:20 — valid CLASS slot, no IDs → only constant checks
        $result = $this->validator->check([
            'grade'       => 9,
            'day_of_week' => 'Tuesday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        $this->assertTrue($result['passes']);
        $this->assertEmpty($result['violations']);
    }

    public function test_check_fails_constant_when_slot_is_g7_friday(): void
    {
        $result = $this->validator->check([
            'grade'       => 7,
            'day_of_week' => 'Friday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        $this->assertFalse($result['passes']);
        $codes = array_column($result['violations'], 'code');
        $this->assertContains('H12', $codes);
    }

    public function test_check_collects_both_constant_and_db_violations(): void
    {
        // Constant violation: G7 Friday (H12)
        // DB violation: H3 (class before default early shift — use 07:00 start)
        // Use a non-Friday + H3 combo: G7 Monday dead zone + H3 outside shift
        $result = $this->validator->check([
            'grade'       => 7,
            'day_of_week' => 'Friday',   // H12 fires
            'start_time'  => '07:00',    // also before 07:30 → H3 fires
            'end_time'    => '07:30',
            'faculty_id'  => self::DUMMY_ID,  // trigger H3 check
        ]);

        $this->assertFalse($result['passes']);
        $codes = array_column($result['violations'], 'code');
        $this->assertContains('H12', $codes, 'H12 (Friday ILA) should fire');
        $this->assertContains('H3',  $codes, 'H3 (official time) should fire');
    }

    public function test_check_excludes_schedule_id_for_update_scenario(): void
    {
        // Insert a schedule row and capture its ID
        $id = null;
        $this->withoutFKChecks(function () use (&$id) {
            $id = DB::table('class_schedules')->insertGetId([
                'user_id'          => self::DUMMY_ID + 1,
                'subject_id'       => self::DUMMY_ID,
                'section_id'       => self::DUMMY_ID + 1,
                'classroom_id'     => self::DUMMY_ID,
                'school_year_id'   => self::DUMMY_ID,
                'academic_term_id' => self::DUMMY_ID,
                'day_of_week'      => 'Thursday',
                'start_time'       => '09:30:00',
                'end_time'         => '10:20:00',
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        });

        // When excluded → no H1/H2 conflict for that teacher+section
        $result = $this->validator->check([
            'grade'            => 9,
            'day_of_week'      => 'Thursday',
            'start_time'       => '09:30',
            'end_time'         => '10:20',
            'faculty_id'       => self::DUMMY_ID + 1,
            'section_id'       => self::DUMMY_ID + 1,
            'academic_term_id' => self::DUMMY_ID,
        ], $id);

        $codes = array_column($result['violations'], 'code');
        $this->assertNotContains('H1', $codes, 'H1 must not fire when schedule is excluded');
        $this->assertNotContains('H2', $codes, 'H2 must not fire when schedule is excluded');
    }

    // =========================================================================
    // Helpers for inserting with FK checks disabled
    // =========================================================================

    /** Insert a schedule row and return its auto-increment id. */
    private function insertScheduleGetId(array $overrides = []): int
    {
        $attrs = array_merge([
            'user_id'          => self::DUMMY_ID,
            'subject_id'       => self::DUMMY_ID,
            'section_id'       => self::DUMMY_ID,
            'classroom_id'     => self::DUMMY_ID,
            'school_year_id'   => self::DUMMY_ID,
            'academic_term_id' => self::DUMMY_ID,
            'day_of_week'      => 'Thursday',
            'start_time'       => '09:30:00',
            'end_time'         => '10:20:00',
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides);

        $id = null;
        $this->withoutFKChecks(function () use ($attrs, &$id) {
            $id = DB::table('class_schedules')->insertGetId($attrs);
        });

        return $id;
    }
}
