<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\SchedulingConstants as SC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Grade 10's Tue-Fri elective window is identified by tagging one CLASS
 * period's label with "(Elective)" — it's not a distinct period type of its
 * own, since G10 shares its raw timetable with G9 (see
 * SchedulingConstants::getTueFriTimetable()). An admin can edit G10's Tue-Fri
 * bell schedule via the calendar's inline editor
 * (BellScheduleController::update()), which stores a full replacement row
 * set in bell_schedule_overrides — preserving every row's label but freely
 * shifting start/end times.
 *
 * Found 2026-07-20: the original tagging logic matched by a hardcoded start
 * time ('13:50'), so a live G10 override whose periods had all shifted no
 * longer had any row starting at exactly that time — the elective tag
 * silently never applied, and the amber Electives band vanished from every
 * G10 homeroom's calendar despite the window still existing (just at a
 * different time). Fixed to match by period label ("Period 7"), which
 * survives a time-only edit since the editor always resubmits existing
 * labels unchanged.
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
        // Mirrors a real production override: every period shifted ~30
        // minutes later than the hardcoded default, labels unchanged.
        DB::table('bell_schedule_overrides')->insert([
            'timetable_key' => 'TUEFRI_730_G9G10',
            'rows' => json_encode([
                ['start' => '07:30', 'end' => '08:20', 'type' => 'CLASS', 'label' => 'Period 1'],
                ['start' => '08:20', 'end' => '09:10', 'type' => 'CLASS', 'label' => 'Period 2'],
                ['start' => '09:10', 'end' => '09:30', 'type' => 'RECESS', 'label' => 'Recess'],
                ['start' => '09:30', 'end' => '10:20', 'type' => 'CLASS', 'label' => 'Period 3'],
                ['start' => '10:20', 'end' => '11:10', 'type' => 'CLASS', 'label' => 'Period 4'],
                ['start' => '11:10', 'end' => '12:10', 'type' => 'LUNCH', 'label' => 'Lunch Break'],
                ['start' => '12:10', 'end' => '13:00', 'type' => 'CLASS', 'label' => 'Period 5'],
                ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS', 'label' => 'Period 6'],
                ['start' => '14:20', 'end' => '15:10', 'type' => 'CLASS', 'label' => 'Period 7'],
                ['start' => '15:10', 'end' => '16:00', 'type' => 'CLASS', 'label' => 'Period 8'],
                ['start' => '16:00', 'end' => '17:00', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
            ]),
        ]);

        $windows = SC::getElectiveWindows(10, 'Tuesday');

        $this->assertNotEmpty($windows, 'G10 must still have an elective window after a bell-schedule time shift');
        $this->assertSame('14:20', $windows[0]['start']);
        $this->assertSame('15:10', $windows[0]['end']);
        $this->assertSame('Electives', $windows[0]['label']);
    }

    public function test_grade_9_never_gets_an_elective_window_even_with_the_same_override(): void
    {
        // G9 shares the raw TUEFRI_730_G9G10 timetable with G10 but must
        // never be tagged — tagElectivePeriod() is only invoked for grade 10.
        DB::table('bell_schedule_overrides')->insert([
            'timetable_key' => 'TUEFRI_730_G9G10',
            'rows' => json_encode([
                ['start' => '07:30', 'end' => '08:20', 'type' => 'CLASS', 'label' => 'Period 1'],
                ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS', 'label' => 'Period 6'],
                ['start' => '14:20', 'end' => '15:10', 'type' => 'CLASS', 'label' => 'Period 7'],
            ]),
        ]);

        $this->assertEmpty(SC::getElectiveWindows(9, 'Tuesday'));
    }

    public function test_elective_window_still_works_with_no_override_present(): void
    {
        // No-override path (the common case) — default TUEFRI_730_G9G10,
        // Period 7 at its hardcoded 13:50-14:40.
        $windows = SC::getElectiveWindows(10, 'Tuesday');

        $this->assertNotEmpty($windows);
        $this->assertSame('13:50', $windows[0]['start']);
        $this->assertSame('14:40', $windows[0]['end']);
    }
}
