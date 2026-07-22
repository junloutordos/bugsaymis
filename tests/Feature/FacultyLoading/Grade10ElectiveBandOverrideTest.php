<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\SchedulingConstants as SC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Grade 10's elective window is fixed at 12:10-13:10 on Wednesday, Thursday,
 * and Friday only — Tuesday is a plain, non-elective day, identical to Grade
 * 9 (see SchedulingConstants::applyG10ElectiveWindow()). It's identified by
 * tagging the "Period 5" CLASS row's label rather than matching a period type
 * of its own, since G10 shares its raw timetable with G9 (see
 * SchedulingConstants::getTueFriTimetable()). An admin can edit G10's Tue-Fri
 * bell schedule via the calendar's inline editor
 * (BellScheduleController::update()), which stores a full replacement row set
 * in bell_schedule_overrides — preserving every row's label but freely
 * shifting start/end times. Because the elective window is meant to be fixed
 * (unlike a normal class period), it overwrites whatever start/end an
 * override sets on "Period 5" rather than tracking it.
 *
 * Found 2026-07-20: the original tagging logic matched by a hardcoded start
 * time ('13:50'), so a live G10 override whose periods had all shifted no
 * longer had any row starting at exactly that time — the elective tag
 * silently never applied. Fixed to match by period label, which survives a
 * time-only edit since the editor always resubmits existing labels unchanged.
 *
 * Changed 2026-07-22: retargeted from "Period 7" (tagged on all four Tue-Fri
 * days) to "Period 5" on Wed/Thu/Fri only, at a fixed 12:10-13:10 — no G10
 * elective class had ever actually run in the old Period 7 window; the real
 * classes sit at this slot on three of the four days.
 */
class Grade10ElectiveBandOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // SchedulingConstants caches overrides in a static, request-scoped
        // variable — RefreshDatabase resets the DB between tests but not
        // this, so it must be flushed explicitly or an earlier test's
        // override leaks into a later test running in the same process.
        SC::flushOverrideCache();
    }

    public function test_elective_window_survives_a_bell_schedule_time_shift(): void
    {
        // Mirrors a real production override: every period shifted, labels
        // unchanged. Period 5 no longer starts at the hardcoded 12:10.
        DB::table('bell_schedule_overrides')->insert([
            'timetable_key' => 'TUEFRI_730_G9G10',
            'rows' => json_encode([
                ['start' => '07:30', 'end' => '08:20', 'type' => 'CLASS', 'label' => 'Period 1'],
                ['start' => '08:20', 'end' => '09:10', 'type' => 'CLASS', 'label' => 'Period 2'],
                ['start' => '09:10', 'end' => '09:30', 'type' => 'RECESS', 'label' => 'Recess'],
                ['start' => '09:30', 'end' => '10:20', 'type' => 'CLASS', 'label' => 'Period 3'],
                ['start' => '10:20', 'end' => '11:10', 'type' => 'CLASS', 'label' => 'Period 4'],
                ['start' => '11:20', 'end' => '12:20', 'type' => 'LUNCH', 'label' => 'Lunch Break'],
                ['start' => '12:20', 'end' => '13:10', 'type' => 'CLASS', 'label' => 'Period 5'],
                ['start' => '13:40', 'end' => '14:30', 'type' => 'CLASS', 'label' => 'Period 6'],
                ['start' => '14:30', 'end' => '15:20', 'type' => 'CLASS', 'label' => 'Period 7'],
                ['start' => '15:20', 'end' => '16:10', 'type' => 'CLASS', 'label' => 'Period 8'],
                ['start' => '16:10', 'end' => '17:00', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
            ]),
        ]);

        // The window stays fixed at 12:10-13:10 regardless of where the
        // override put Period 5 — this is a deliberately fixed elective
        // window, not one that tracks the underlying period's time.
        $windows = SC::getElectiveWindows(10, 'Wednesday');

        $this->assertNotEmpty($windows, 'G10 must still have an elective window after a bell-schedule time shift');
        $this->assertSame('12:10', $windows[0]['start']);
        $this->assertSame('13:10', $windows[0]['end']);
        $this->assertSame('Electives', $windows[0]['label']);
    }

    public function test_grade_9_never_gets_an_elective_window_even_with_the_same_override(): void
    {
        // G9 shares the raw TUEFRI_730_G9G10 timetable with G10 but must
        // never be tagged — applyG10ElectiveWindow() is only invoked for
        // grade 10.
        DB::table('bell_schedule_overrides')->insert([
            'timetable_key' => 'TUEFRI_730_G9G10',
            'rows' => json_encode([
                ['start' => '07:30', 'end' => '08:20', 'type' => 'CLASS', 'label' => 'Period 1'],
                ['start' => '12:10', 'end' => '13:00', 'type' => 'CLASS', 'label' => 'Period 5'],
            ]),
        ]);

        $this->assertEmpty(SC::getElectiveWindows(9, 'Wednesday'));
    }

    public function test_grade_10_has_no_elective_window_on_tuesday(): void
    {
        // Tuesday is a plain, non-elective day for G10 — only Wed/Thu/Fri
        // carry the fixed elective window.
        $this->assertEmpty(SC::getElectiveWindows(10, 'Tuesday'));
    }

    public function test_elective_window_present_on_wednesday_thursday_and_friday(): void
    {
        foreach (['Wednesday', 'Thursday', 'Friday'] as $day) {
            $windows = SC::getElectiveWindows(10, $day);

            $this->assertNotEmpty($windows, "G10 must have an elective window on {$day}");
            $this->assertSame('12:10', $windows[0]['start']);
            $this->assertSame('13:10', $windows[0]['end']);
        }
    }

    public function test_elective_window_still_works_with_no_override_present(): void
    {
        // No-override path (the common case) — default TUEFRI_730_G9G10,
        // Period 5 relabelled and fixed at 12:10-13:10.
        $windows = SC::getElectiveWindows(10, 'Wednesday');

        $this->assertNotEmpty($windows);
        $this->assertSame('12:10', $windows[0]['start']);
        $this->assertSame('13:10', $windows[0]['end']);
    }
}
