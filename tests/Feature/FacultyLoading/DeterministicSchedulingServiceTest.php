<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\DeterministicSchedulingService;
use ReflectionClass;
use Tests\TestCase;

class DeterministicSchedulingServiceTest extends TestCase
{
    private DeterministicSchedulingService $service;

    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeterministicSchedulingService;
        $this->reflection = new ReflectionClass($this->service);
        $this->invokePrivate('buildGrids', [[8]]);
    }

    public function test_regular_session_cannot_use_grade_8_ilp_only_overflow(): void
    {
        $grid = $this->property('gridByGrade')[8];
        $reserved = array_values(array_filter(
            $grid,
            fn ($slot) => $slot['day'] === 'Monday' && ! $slot['is_ilp_only'],
        ));

        $slot = $this->invokePrivate('findBestSlot', [$this->makeSession('regular', 'Monday'), $reserved]);

        $this->assertNull($slot);
    }

    public function test_ilp_session_can_use_grade_8_ilp_only_overflow(): void
    {
        $grid = $this->property('gridByGrade')[8];
        $reserved = array_values(array_filter(
            $grid,
            fn ($slot) => $slot['day'] === 'Monday' && ! $slot['is_ilp_only'],
        ));

        $slot = $this->invokePrivate('findBestSlot', [$this->makeSession('ilp', 'Monday'), $reserved]);

        $this->assertSame('16:00', $slot['start']);
        $this->assertSame('16:30', $slot['end']);
        $this->assertTrue($slot['is_ilp_only']);
    }

    public function test_overflow_is_ranked_after_standard_periods(): void
    {
        $slot = $this->invokePrivate('findBestSlot', [$this->makeSession('ilp'), []]);

        $this->assertFalse($slot['is_overflow']);
    }

    public function test_heavy_subject_prefixes_are_recognized(): void
    {
        $this->assertTrue($this->invokePrivate('isHeavySubject', ['MATH1']));
        $this->assertTrue($this->invokePrivate('isHeavySubject', ['bio3l2']));
        $this->assertFalse($this->invokePrivate('isHeavySubject', ['ENG2']));
        $this->assertFalse($this->invokePrivate('isHeavySubject', ['']));
    }

    public function test_heavy_subject_afternoon_slot_scores_worse_than_morning(): void
    {
        $s = ['section_id' => 1, 'subject_id' => 1, 'subject_code' => 'MATH1', 'faculty_id' => -1];
        $am = ['day' => 'Monday', 'start_min' => 480, 'end_min' => 530, 'is_overflow' => false];
        $pm = ['day' => 'Monday', 'start_min' => 780, 'end_min' => 830, 'is_overflow' => false];

        $scoreAm = $this->invokePrivate('computeSlotScore', [$s, $am, $am, false]);
        $scorePm = $this->invokePrivate('computeSlotScore', [$s, $pm, $pm, false]);

        $this->assertGreaterThan($scoreAm, $scorePm);
    }

    public function test_faculty_day_spread_prefers_the_less_used_day(): void
    {
        $this->reflection->getProperty('facultyDayCount')->setValue($this->service, [5 => ['Monday' => 2]]);

        $s = ['section_id' => 1, 'subject_id' => 2, 'subject_code' => 'ENG1', 'faculty_id' => 5];
        $monday  = ['day' => 'Monday', 'start_min' => 480, 'end_min' => 530, 'is_overflow' => false];
        $tuesday = ['day' => 'Tuesday', 'start_min' => 480, 'end_min' => 530, 'is_overflow' => false];

        $scoreMonday  = $this->invokePrivate('computeSlotScore', [$s, $monday, $monday, false]);
        $scoreTuesday = $this->invokePrivate('computeSlotScore', [$s, $tuesday, $tuesday, false]);

        $this->assertGreaterThan($scoreTuesday, $scoreMonday);
    }

    public function test_faculty_gap_penalty_rewards_filling_a_small_gap(): void
    {
        $this->reflection->getProperty('facultyBusy')->setValue($this->service, [
            7 => ['Monday' => [[480, 600, -1]]], // 08:00-10:00 already booked
        ]);

        // A 30-minute gap after the existing block (10:30-11:00): some idle
        // time, but nowhere near the fatigue-streak cap.
        $smallGap = ['start_min' => 630, 'end_min' => 660];
        $penalty  = $this->invokePrivate('facultyDayGapPenalty', [7, 'Monday', $smallGap]);

        $this->assertSame(2, $penalty); // intdiv(30 idle minutes, 15)
    }

    public function test_faculty_gap_penalty_flips_once_streak_exceeds_fatigue_cap(): void
    {
        $this->reflection->getProperty('facultyBusy')->setValue($this->service, [
            7 => ['Monday' => [[480, 600, -1]]], // 08:00-10:00 already booked
        ]);

        // Placed immediately after the existing block with zero gap: the
        // merged contiguous run becomes 08:00-12:00 = 240 min, past the
        // 180-minute fatigue guideline.
        $contiguous = ['start_min' => 600, 'end_min' => 720];
        $penalty    = $this->invokePrivate('facultyDayGapPenalty', [7, 'Monday', $contiguous]);

        $this->assertSame(20, $penalty);
    }

    public function test_faculty_gap_penalty_is_zero_for_tba_placeholder(): void
    {
        $penalty = $this->invokePrivate('facultyDayGapPenalty', [-42, 'Monday', ['start_min' => 480, 'end_min' => 530]]);

        $this->assertSame(0, $penalty);
    }

    public function test_commit_and_uncommit_keep_faculty_day_count_symmetric(): void
    {
        $s = ['section_id' => 1, 'subject_id' => 1, 'faculty_id' => 9, 'session_type' => 'regular'];
        $slot = ['day' => 'Monday', 'start' => '08:00', 'end' => '08:50', 'start_min' => 480, 'end_min' => 530];

        $idx = $this->invokePrivate('commit', [$s, $slot]);
        $this->assertSame(1, $this->property('facultyDayCount')[9]['Monday']);

        $this->invokePrivate('uncommit', [$idx]);
        $this->assertSame(0, $this->property('facultyDayCount')[9]['Monday']);
    }

    private function makeSession(string $type, ?string $forcedDay = null): array
    {
        return [
            'grade' => 8,
            'section_id' => 1,
            'subject_id' => 1,
            'faculty_id' => -1,
            'session_type' => $type,
            'is_elective' => false,
            'forced_day' => $forcedDay,
        ];
    }

    private function invokePrivate(string $method, array $arguments): mixed
    {
        return $this->reflection->getMethod($method)->invokeArgs($this->service, $arguments);
    }

    private function property(string $name): mixed
    {
        return $this->reflection->getProperty($name)->getValue($this->service);
    }
}
