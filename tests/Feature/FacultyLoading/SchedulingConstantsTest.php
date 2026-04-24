<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\SchedulingConstants as SC;
use Tests\TestCase;

/**
 * Unit tests for SchedulingConstants.
 *
 * These tests verify the canonical timetable data that the entire
 * scheduling engine depends on. If any constant is wrong, every
 * downstream phase will produce incorrect schedules.
 */
class SchedulingConstantsTest extends TestCase
{
    // ── Grade Group mapping ───────────────────────────────────────────────────

    public function test_grade_group_mapping(): void
    {
        $this->assertSame('G7G8',   SC::getGradeGroup(7));
        $this->assertSame('G7G8',   SC::getGradeGroup(8));
        $this->assertSame('G9G10',  SC::getGradeGroup(9));
        $this->assertSame('G9G10',  SC::getGradeGroup(10));
        $this->assertSame('G11G12', SC::getGradeGroup(11));
        $this->assertSame('G11G12', SC::getGradeGroup(12));
    }

    // ── Grade Sections ────────────────────────────────────────────────────────

    public function test_grade_sections_counts(): void
    {
        $this->assertCount(4, SC::GRADE_SECTIONS[7]);
        $this->assertCount(4, SC::GRADE_SECTIONS[8]);
        $this->assertCount(4, SC::GRADE_SECTIONS[9]);
        $this->assertCount(4, SC::GRADE_SECTIONS[10]);
        $this->assertCount(3, SC::GRADE_SECTIONS[11]);
        $this->assertCount(3, SC::GRADE_SECTIONS[12]);
    }

    public function test_grade_7_section_names(): void
    {
        $this->assertSame(
            ['Aquamarine', 'Opal', 'Turquoise', 'Sapphire'],
            SC::GRADE_SECTIONS[7]
        );
    }

    public function test_grade_11_section_names(): void
    {
        $this->assertSame(['Venus', 'Mars', 'Mercury'], SC::GRADE_SECTIONS[11]);
    }

    // ── Lunch Windows ─────────────────────────────────────────────────────────

    /** @dataProvider lunchProvider */
    public function test_lunch_windows(int $grade, string $day, string $expectedStart, string $expectedEnd): void
    {
        $lunch = SC::getLunch($grade, $day);
        $this->assertSame($expectedStart, $lunch['start'], "Lunch start mismatch for G{$grade} {$day}");
        $this->assertSame($expectedEnd,   $lunch['end'],   "Lunch end mismatch for G{$grade} {$day}");
    }

    public static function lunchProvider(): array
    {
        return [
            // Monday windows
            'G7 Monday'  => [7,  'Monday',    '11:40', '12:40'],
            'G8 Monday'  => [8,  'Monday',    '11:40', '12:40'],
            'G9 Monday'  => [9,  'Monday',    '10:50', '11:50'],
            'G10 Monday' => [10, 'Monday',    '10:50', '11:50'],
            'G11 Monday' => [11, 'Monday',    '12:30', '13:30'],
            'G12 Monday' => [12, 'Monday',    '12:30', '13:30'],
            // Tue-Fri windows
            'G7 Tuesday'   => [7,  'Tuesday',  '10:20', '11:20'],
            'G8 Thursday'  => [8,  'Thursday', '10:20', '11:20'],
            'G9 Wednesday' => [9,  'Wednesday','11:10', '12:10'],
            'G10 Friday'   => [10, 'Friday',   '11:10', '12:10'],
            'G11 Tuesday'  => [11, 'Tuesday',  '12:00', '13:00'],
            'G12 Thursday' => [12, 'Thursday', '12:00', '13:00'],
        ];
    }

    public function test_lunch_windows_are_staggered_across_grade_groups(): void
    {
        // The staggered lunch system means G7/G8, G9/G10, G11/G12
        // each have different lunch times — so a teacher CAN span grades.
        $g7Lunch  = SC::getLunch(7, 'Tuesday');
        $g9Lunch  = SC::getLunch(9, 'Tuesday');
        $g11Lunch = SC::getLunch(11, 'Tuesday');

        // All three should be different start times
        $starts = [$g7Lunch['start'], $g9Lunch['start'], $g11Lunch['start']];
        $this->assertCount(3, array_unique($starts), 'Lunch windows must be staggered across grade groups');
    }

    // ── Recess Windows ────────────────────────────────────────────────────────

    /** @dataProvider recessProvider */
    public function test_recess_windows(int $grade, string $day, int $expectedCount, string $firstStart): void
    {
        $recesses = SC::getRecess($grade, $day);
        $this->assertCount($expectedCount, $recesses, "Recess count mismatch for G{$grade} {$day}");
        if ($expectedCount > 0) {
            $this->assertSame($firstStart, $recesses[0]['start']);
        }
    }

    public static function recessProvider(): array
    {
        return [
            'G7 Monday'   => [7,  'Monday',    1, '09:40'],
            'G8 Monday'   => [8,  'Monday',    1, '09:40'],
            'G9 Monday'   => [9,  'Monday',    1, '09:40'],
            'G10 Monday'  => [10, 'Monday',    2, '09:40'],  // two recesses on Monday
            'G11 Monday'  => [11, 'Monday',    1, '10:30'],
            'G12 Monday'  => [12, 'Monday',    1, '10:30'],
            'G7 Tuesday'  => [7,  'Tuesday',   2, '08:20'],  // morning + afternoon
            'G8 Thursday' => [8,  'Thursday',  2, '08:20'],
            'G9 Tuesday'  => [9,  'Tuesday',   2, '09:10'],
            'G10 Friday'  => [10, 'Friday',    2, '09:10'],
            'G11 Tuesday' => [11, 'Tuesday',   1, '10:00'],
            'G12 Thursday'=> [12, 'Thursday',  1, '10:00'],
        ];
    }

    // ── Monday Timetables ─────────────────────────────────────────────────────

    public function test_monday_g7g8_starts_with_flag(): void
    {
        $slots = SC::MONDAY_G7G8;
        $this->assertSame('FLAG',  $slots[0]['type']);
        $this->assertSame('07:30', $slots[0]['start']);
        $this->assertSame('08:00', $slots[0]['end']);
    }

    public function test_monday_g7g8_has_dead_zone_after_homeroom(): void
    {
        // H9: G7-G8 Monday 8:50-9:40 must be DEAD (no classes)
        $slots = SC::MONDAY_G7G8;
        $dead  = array_filter($slots, fn ($s) => $s['type'] === 'DEAD');
        $this->assertNotEmpty($dead, 'G7/G8 Monday must have a DEAD zone');

        $deadSlot = array_values($dead)[0];
        $this->assertSame('08:50', $deadSlot['start']);
        $this->assertSame('09:40', $deadSlot['end']);
    }

    public function test_monday_g7g8_has_no_class_before_10am(): void
    {
        $classSlots = SC::getClassSlots(7, 'Monday');
        foreach ($classSlots as $slot) {
            $this->assertGreaterThanOrEqual('10:00', $slot['start'],
                'G7 Monday classes must not start before 10:00'
            );
        }
    }

    public function test_monday_g9g10_has_class_at_08_50(): void
    {
        // G9/G10 start Period 1 at 08:50 on Monday (unlike G7/G8)
        $classSlots = SC::getClassSlots(9, 'Monday');
        $starts     = array_column($classSlots, 'start');
        $this->assertContains('08:50', $starts, 'G9 Monday Period 1 should start at 08:50');
    }

    public function test_monday_g11g12_uses_advising_not_homeroom(): void
    {
        $slots    = SC::MONDAY_G11G12;
        $advisory = array_filter($slots, fn ($s) => $s['type'] === 'ADVISING');
        $homeroom = array_filter($slots, fn ($s) => $s['type'] === 'HOMEROOM');

        $this->assertNotEmpty($advisory, 'G11/G12 Monday must have ADVISING slot');
        $this->assertEmpty($homeroom,    'G11/G12 Monday must NOT have HOMEROOM slot');
    }

    public function test_flag_ceremony_present_on_all_monday_timetables(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            $timetable = SC::getMondayTimetable($grade);
            $flags     = array_filter($timetable, fn ($s) => $s['type'] === 'FLAG');
            $this->assertNotEmpty($flags, "Grade {$grade} Monday must have FLAG slot");
            $flag = array_values($flags)[0];
            $this->assertSame('07:30', $flag['start']);
            $this->assertSame('08:00', $flag['end']);
        }
    }

    // ── Tue–Fri Timetables ────────────────────────────────────────────────────

    public function test_tuefri_g7g8_has_morning_and_afternoon_recess(): void
    {
        $timetable = SC::TUEFRI_730_G7G8;
        $recesses  = array_filter($timetable, fn ($s) => $s['type'] === 'RECESS');
        $this->assertCount(2, $recesses, 'G7/G8 Tue-Fri should have 2 recess slots');
    }

    public function test_tuefri_g11g12_has_consultation_at_end(): void
    {
        $timetable = SC::TUEFRI_730_G11G12;
        $last      = end($timetable);
        $this->assertSame('CONSULT', $last['type']);
        $this->assertSame('15:30', $last['start']);
    }

    public function test_no_class_slots_overlap_lunch_in_any_timetable(): void
    {
        $combos = [
            [7, 'Monday'], [7, 'Tuesday'], [8, 'Thursday'],
            [9, 'Monday'], [9, 'Tuesday'], [10, 'Friday'],
            [11, 'Monday'], [11, 'Tuesday'], [12, 'Thursday'],
        ];

        foreach ($combos as [$grade, $day]) {
            $lunch      = SC::getLunch($grade, $day);
            $classSlots = SC::getClassSlots($grade, $day);

            foreach ($classSlots as $slot) {
                $overlap = SC::timesOverlap(
                    $slot['start'], $slot['end'],
                    $lunch['start'], $lunch['end']
                );
                $this->assertFalse($overlap,
                    "G{$grade} {$day}: Class slot {$slot['start']}–{$slot['end']} overlaps lunch {$lunch['start']}–{$lunch['end']}"
                );
            }
        }
    }

    // ── getClassSlots helper ──────────────────────────────────────────────────

    public function test_get_class_slots_returns_only_class_type(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            foreach (['Monday', 'Tuesday', 'Thursday'] as $day) {
                $slots = SC::getClassSlots($grade, $day);
                foreach ($slots as $slot) {
                    $this->assertSame('CLASS', $slot['type'],
                        "getClassSlots for G{$grade} {$day} returned non-CLASS slot"
                    );
                }
            }
        }
    }

    public function test_class_slots_have_required_keys(): void
    {
        $slots = SC::getClassSlots(7, 'Tuesday');
        $this->assertNotEmpty($slots);
        foreach ($slots as $slot) {
            $this->assertArrayHasKey('start', $slot);
            $this->assertArrayHasKey('end',   $slot);
            $this->assertArrayHasKey('type',  $slot);
            $this->assertArrayHasKey('label', $slot);
        }
    }

    // ── Wednesday / Friday special rules ────────────────────────────────────

    public function test_wednesday_activity_start_defined_for_all_grade_groups(): void
    {
        $this->assertArrayHasKey('G7G8',   SC::WEDNESDAY_ACTIVITY_START);
        $this->assertArrayHasKey('G9G10',  SC::WEDNESDAY_ACTIVITY_START);
        $this->assertArrayHasKey('G11G12', SC::WEDNESDAY_ACTIVITY_START);
    }

    public function test_wednesday_wellness_block_defined(): void
    {
        $this->assertSame('09:50', SC::WEDNESDAY_WELLNESS['start']);
        $this->assertSame('10:20', SC::WEDNESDAY_WELLNESS['end']);
    }

    public function test_friday_ila_applies_only_to_g7_and_g8(): void
    {
        $this->assertContains(7, SC::FRIDAY_ILA_GRADES);
        $this->assertContains(8, SC::FRIDAY_ILA_GRADES);
        $this->assertNotContains(9,  SC::FRIDAY_ILA_GRADES);
        $this->assertNotContains(10, SC::FRIDAY_ILA_GRADES);
        $this->assertNotContains(11, SC::FRIDAY_ILA_GRADES);
        $this->assertNotContains(12, SC::FRIDAY_ILA_GRADES);
    }

    // ── Science Core ─────────────────────────────────────────────────────────

    public function test_science_core_g11_covers_all_sections(): void
    {
        $sections = SC::GRADE_SECTIONS[11];
        foreach ($sections as $section) {
            $this->assertArrayHasKey($section, SC::SCIENCE_CORE_G11,
                "Section {$section} missing from SCIENCE_CORE_G11"
            );
        }
    }

    public function test_science_core_g12_covers_all_sections(): void
    {
        $sections = SC::GRADE_SECTIONS[12];
        foreach ($sections as $section) {
            $this->assertArrayHasKey($section, SC::SCIENCE_CORE_G12,
                "Section {$section} missing from SCIENCE_CORE_G12"
            );
        }
    }

    public function test_science_core_g11_subjects_are_distinct(): void
    {
        $subjects = array_values(SC::SCIENCE_CORE_G11);
        $this->assertCount(count(array_unique($subjects)), $subjects,
            'Each G11 section must have a different science core subject'
        );
    }

    // ── timesOverlap utility ─────────────────────────────────────────────────

    public function test_times_overlap_detects_overlap(): void
    {
        $this->assertTrue(SC::timesOverlap('08:00', '09:00', '08:30', '09:30'));
        $this->assertTrue(SC::timesOverlap('08:00', '09:00', '07:30', '08:30'));
    }

    public function test_times_overlap_detects_no_overlap(): void
    {
        $this->assertFalse(SC::timesOverlap('08:00', '09:00', '09:00', '10:00'));
        $this->assertFalse(SC::timesOverlap('10:00', '11:00', '08:00', '10:00'));
    }

    // ── toMinutes / fromMinutes ───────────────────────────────────────────────

    public function test_to_and_from_minutes_roundtrip(): void
    {
        $times = ['07:30', '08:00', '10:20', '12:00', '13:00', '16:30', '17:00'];
        foreach ($times as $t) {
            $this->assertSame($t, SC::fromMinutes(SC::toMinutes($t)));
        }
    }
}
