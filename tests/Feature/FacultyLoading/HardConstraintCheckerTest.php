<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\HardConstraintChecker as HC;
use App\Services\FacultyLoading\SchedulingConstants as SC;
use Tests\TestCase;

/**
 * Tests for HardConstraintChecker — Phase 4 special-day locks.
 *
 * Covers H9 (Monday dead zone), H10 (Wednesday Activity lock),
 * H11 (Wednesday Wellness block), H12 (Friday ILA), and the
 * composite checkSpecialDayLocks().
 */
class HardConstraintCheckerTest extends TestCase
{
    // ── Result shape helpers ──────────────────────────────────────────────────

    private function assertPasses(array $result, string $context = ''): void
    {
        $msg = $context ? "Expected PASS for: {$context}" : 'Expected constraint to pass';
        $this->assertTrue($result['passes'], $msg . ' — got: ' . ($result['reason'] ?? ''));
        $this->assertNull($result['reason']);
    }

    private function assertFails(array $result, string $context = ''): void
    {
        $msg = $context ? "Expected FAIL for: {$context}" : 'Expected constraint to fail';
        $this->assertFalse($result['passes'], $msg);
        $this->assertIsString($result['reason']);
        $this->assertNotEmpty($result['reason']);
    }

    // =========================================================================
    // H9 — Monday dead zone (G7/G8 only, 08:50–09:40)
    // =========================================================================

    public function test_h9_fails_for_g7_monday_in_dead_zone(): void
    {
        $this->assertFails(
            HC::checkMondayDeadZone(7, 'Monday', '08:50', '09:40'),
            'G7 Monday 08:50–09:40 (exact dead zone)'
        );
    }

    public function test_h9_fails_for_g8_monday_overlapping_dead_zone(): void
    {
        // Class starting before dead zone but ending inside it
        $this->assertFails(
            HC::checkMondayDeadZone(8, 'Monday', '08:20', '09:10'),
            'G8 Monday 08:20–09:10 (overlaps dead zone start)'
        );
    }

    public function test_h9_fails_when_class_spans_entire_dead_zone(): void
    {
        $this->assertFails(
            HC::checkMondayDeadZone(7, 'Monday', '08:00', '10:00'),
            'G7 Monday 08:00–10:00 (contains dead zone)'
        );
    }

    public function test_h9_passes_for_g7_monday_class_before_dead_zone(): void
    {
        // CLASS period before dead zone
        $this->assertPasses(
            HC::checkMondayDeadZone(7, 'Monday', '08:00', '08:50'),
            'G7 Monday 08:00–08:50 (ends exactly at dead zone start)'
        );
    }

    public function test_h9_passes_for_g7_monday_class_after_dead_zone(): void
    {
        $this->assertPasses(
            HC::checkMondayDeadZone(7, 'Monday', '09:40', '10:30'),
            'G7 Monday 09:40–10:30 (starts exactly at dead zone end)'
        );
    }

    public function test_h9_passes_for_g9_monday_in_dead_zone_window(): void
    {
        // G9 has no dead zone — 08:50–09:40 is Period 1 for G9
        $this->assertPasses(
            HC::checkMondayDeadZone(9, 'Monday', '08:50', '09:40'),
            'G9 Monday 08:50–09:40 (valid Period 1, no dead zone for G9)'
        );
    }

    public function test_h9_passes_for_g7_on_non_monday(): void
    {
        $this->assertPasses(
            HC::checkMondayDeadZone(7, 'Tuesday', '08:50', '09:40'),
            'G7 Tuesday (dead zone rule only applies Monday)'
        );
    }

    // =========================================================================
    // H10 — Wednesday Activity lock
    // =========================================================================

    public function test_h10_fails_for_g7_wednesday_class_at_activity_start(): void
    {
        // G7G8 activity starts at 15:00
        $this->assertFails(
            HC::checkWednesdayActivityLock(7, 'Wednesday', '15:00'),
            'G7 Wednesday class starting exactly at 15:00'
        );
    }

    public function test_h10_fails_for_g7_wednesday_class_after_activity_start(): void
    {
        $this->assertFails(
            HC::checkWednesdayActivityLock(7, 'Wednesday', '15:30'),
            'G7 Wednesday class starting at 15:30 (after 15:00 lock)'
        );
    }

    public function test_h10_fails_for_g11_wednesday_class_at_12_20(): void
    {
        // G11G12 activity starts at 12:20
        $this->assertFails(
            HC::checkWednesdayActivityLock(11, 'Wednesday', '12:20'),
            'G11 Wednesday class starting at 12:20 (G11G12 lock)'
        );
    }

    public function test_h10_fails_for_g11_wednesday_class_after_12_20(): void
    {
        $this->assertFails(
            HC::checkWednesdayActivityLock(11, 'Wednesday', '13:00'),
            'G11 Wednesday class starting at 13:00'
        );
    }

    public function test_h10_passes_for_g7_wednesday_class_before_15_00(): void
    {
        $this->assertPasses(
            HC::checkWednesdayActivityLock(7, 'Wednesday', '14:00'),
            'G7 Wednesday class at 14:00 (before 15:00 lock)'
        );
    }

    public function test_h10_passes_for_g11_wednesday_class_before_12_20(): void
    {
        $this->assertPasses(
            HC::checkWednesdayActivityLock(11, 'Wednesday', '11:10'),
            'G11 Wednesday class at 11:10 (before 12:20 lock)'
        );
    }

    public function test_h10_passes_for_g7_on_non_wednesday(): void
    {
        $this->assertPasses(
            HC::checkWednesdayActivityLock(7, 'Thursday', '15:30'),
            'G7 Thursday 15:30 (H10 only applies on Wednesday)'
        );
    }

    public function test_h10_end_variant_fails_when_class_runs_into_activity_window(): void
    {
        // Class 14:00–15:30 on G7 Wednesday — end 15:30 exceeds lock at 15:00
        $this->assertFails(
            HC::checkWednesdayActivityLockEnd(7, 'Wednesday', '14:00', '15:30'),
            'G7 Wednesday 14:00–15:30 (end exceeds activity lock at 15:00)'
        );
    }

    public function test_h10_end_variant_passes_when_class_ends_before_activity_start(): void
    {
        // Class 14:00–15:00 on G7 Wednesday — ends exactly at lock
        $this->assertPasses(
            HC::checkWednesdayActivityLockEnd(7, 'Wednesday', '14:00', '15:00'),
            'G7 Wednesday 14:00–15:00 (ends exactly at lock boundary)'
        );
    }

    public function test_h10_g9_and_g10_share_15_00_lock(): void
    {
        // G9G10 also lock at 15:00
        $this->assertFails(
            HC::checkWednesdayActivityLock(9,  'Wednesday', '15:00'),
            'G9 Wednesday lock at 15:00'
        );
        $this->assertFails(
            HC::checkWednesdayActivityLock(10, 'Wednesday', '15:01'),
            'G10 Wednesday class after 15:00'
        );
    }

    // =========================================================================
    // H11 — Wednesday Wellness block (09:50–10:20)
    // =========================================================================

    public function test_h11_fails_when_class_exactly_covers_wellness_block(): void
    {
        $this->assertFails(
            HC::checkWednesdayWellness('Wednesday', '09:50', '10:20'),
            'Wednesday 09:50–10:20 (exact wellness window)'
        );
    }

    public function test_h11_fails_when_class_overlaps_start_of_wellness(): void
    {
        $this->assertFails(
            HC::checkWednesdayWellness('Wednesday', '09:00', '10:00'),
            'Wednesday 09:00–10:00 (overlaps wellness start at 09:50)'
        );
    }

    public function test_h11_fails_when_class_overlaps_end_of_wellness(): void
    {
        $this->assertFails(
            HC::checkWednesdayWellness('Wednesday', '10:00', '11:00'),
            'Wednesday 10:00–11:00 (overlaps wellness end at 10:20)'
        );
    }

    public function test_h11_fails_when_class_contains_entire_wellness_block(): void
    {
        $this->assertFails(
            HC::checkWednesdayWellness('Wednesday', '09:00', '11:00'),
            'Wednesday 09:00–11:00 (contains entire wellness window)'
        );
    }

    public function test_h11_passes_when_class_ends_before_wellness(): void
    {
        $this->assertPasses(
            HC::checkWednesdayWellness('Wednesday', '09:00', '09:50'),
            'Wednesday 09:00–09:50 (ends exactly at wellness start)'
        );
    }

    public function test_h11_passes_when_class_starts_after_wellness(): void
    {
        $this->assertPasses(
            HC::checkWednesdayWellness('Wednesday', '10:20', '11:10'),
            'Wednesday 10:20–11:10 (starts exactly at wellness end)'
        );
    }

    public function test_h11_passes_on_non_wednesday(): void
    {
        $this->assertPasses(
            HC::checkWednesdayWellness('Tuesday', '09:50', '10:20'),
            'Tuesday 09:50–10:20 (wellness only applies Wednesday)'
        );
    }

    public function test_h11_applies_to_all_grades(): void
    {
        foreach ([7, 9, 11] as $grade) {
            // Grade is not a parameter for H11 — wellness applies universally
            $result = HC::checkWednesdayWellness('Wednesday', '09:50', '10:20');
            $this->assertFails($result, "Wednesday wellness for G{$grade}");
        }
    }

    // =========================================================================
    // H12 — G7/G8 Friday ILA
    // =========================================================================

    public function test_h12_fails_for_g7_on_friday(): void
    {
        $this->assertFails(
            HC::checkFridayILA(7, 'Friday'),
            'G7 Friday'
        );
    }

    public function test_h12_fails_for_g8_on_friday(): void
    {
        $this->assertFails(
            HC::checkFridayILA(8, 'Friday'),
            'G8 Friday'
        );
    }

    public function test_h12_passes_for_g9_on_friday(): void
    {
        $this->assertPasses(
            HC::checkFridayILA(9, 'Friday'),
            'G9 Friday (ILA only for G7/G8)'
        );
    }

    public function test_h12_passes_for_all_non_g7g8_grades_on_friday(): void
    {
        foreach ([9, 10, 11, 12] as $grade) {
            $this->assertPasses(
                HC::checkFridayILA($grade, 'Friday'),
                "G{$grade} Friday — should pass"
            );
        }
    }

    public function test_h12_passes_for_g7_on_non_friday(): void
    {
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday'] as $day) {
            $this->assertPasses(
                HC::checkFridayILA(7, $day),
                "G7 {$day} (ILA only on Friday)"
            );
        }
    }

    // =========================================================================
    // checkSpecialDayLocks — composite
    // =========================================================================

    public function test_composite_passes_for_valid_g7_tuesday_class(): void
    {
        // G7 Tuesday Period 6: 14:10–15:00 — no special locks apply
        $this->assertPasses(
            HC::checkSpecialDayLocks(7, 'Tuesday', '14:10', '15:00'),
            'G7 Tuesday 14:10–15:00 (clean slot)'
        );
    }

    public function test_composite_fails_on_h12_for_g7_friday(): void
    {
        $result = HC::checkSpecialDayLocks(7, 'Friday', '08:20', '09:10');
        $this->assertFails($result, 'G7 Friday — H12 should fire');
        $this->assertStringContainsString('H12', $result['reason']);
    }

    public function test_composite_fails_on_h11_for_wednesday_wellness(): void
    {
        $result = HC::checkSpecialDayLocks(9, 'Wednesday', '09:50', '10:40');
        $this->assertFails($result, 'G9 Wednesday overlapping wellness — H11 should fire');
        $this->assertStringContainsString('H11', $result['reason']);
    }

    public function test_composite_fails_on_h10_for_g11_wednesday_afternoon(): void
    {
        $result = HC::checkSpecialDayLocks(11, 'Wednesday', '12:20', '13:10');
        $this->assertFails($result, 'G11 Wednesday after 12:20 — H10 should fire');
        $this->assertStringContainsString('H10', $result['reason']);
    }

    public function test_composite_fails_on_h9_for_g7_monday_dead_zone(): void
    {
        $result = HC::checkSpecialDayLocks(7, 'Monday', '08:50', '09:40');
        $this->assertFails($result, 'G7 Monday dead zone — H9 should fire');
        $this->assertStringContainsString('H9', $result['reason']);
    }

    public function test_composite_passes_for_g11_wednesday_before_activity_lock(): void
    {
        // G11 Wednesday 10:20–11:10 — after wellness (10:20), before lock (12:20)
        $this->assertPasses(
            HC::checkSpecialDayLocks(11, 'Wednesday', '10:20', '11:10'),
            'G11 Wednesday 10:20–11:10 (valid window after wellness, before lock)'
        );
    }

    public function test_composite_h10_fires_before_h11_when_both_could_apply(): void
    {
        // G11 Wednesday: if a class starts at 12:20 AND starts after wellness,
        // H10 should fire (checked before H11 in some sense, but H11 won't
        // fire here since 12:20 > 10:20).  Just verify exactly H10 fires.
        $result = HC::checkSpecialDayLocks(11, 'Wednesday', '12:20', '13:10');
        $this->assertFails($result);
        $this->assertStringContainsString('H10', $result['reason']);
    }

    // =========================================================================
    // checkFixedSlotOverlap — H4–H9 via constants
    // =========================================================================

    public function test_fixed_overlap_fails_for_lunch(): void
    {
        // G7 Tuesday lunch: 10:20–11:20
        $result = HC::checkFixedSlotOverlap(7, 'Tuesday', '10:20', '11:20');
        $this->assertFails($result, 'G7 Tuesday lunch overlap');
    }

    public function test_fixed_overlap_passes_for_valid_class_period(): void
    {
        // G7 Tuesday Period 7: 15:00–15:50
        $result = HC::checkFixedSlotOverlap(7, 'Tuesday', '15:00', '15:50');
        $this->assertPasses($result, 'G7 Tuesday Period 7');
    }

    public function test_fixed_overlap_fails_for_g7_monday_homeroom(): void
    {
        $result = HC::checkFixedSlotOverlap(7, 'Monday', '08:00', '08:50');
        $this->assertFails($result, 'G7 Monday homeroom overlap');
    }

    public function test_fixed_overlap_fails_for_flag_ceremony(): void
    {
        $result = HC::checkFixedSlotOverlap(9, 'Monday', '07:30', '08:00');
        $this->assertFails($result, 'G9 Monday flag ceremony overlap');
    }
}
