<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\ScienceCorePlacementService;
use App\Services\FacultyLoading\SchedulingConstants as SC;
use Tests\TestCase;

/**
 * Tests for ScienceCorePlacementService — Phase 6.
 *
 * Verifies candidate slot counts, H15 validation, placement plan structure,
 * and the Science Core subject mapping.
 */
class ScienceCorePlacementServiceTest extends TestCase
{
    private ScienceCorePlacementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScienceCorePlacementService();
    }

    // =========================================================================
    // getCandidateSlots — counts and structure
    // =========================================================================

    public function test_g11_has_26_candidate_slots(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $this->assertCount(26, $slots,
            'G11 Science Core candidates: Mon(6)+Wed(4)+Thu(8)+Fri(8) = 26'
        );
    }

    public function test_g12_has_26_candidate_slots(): void
    {
        $this->assertCount(26, $this->service->getCandidateSlots(12));
    }

    public function test_candidate_slots_contain_no_tuesday(): void
    {
        foreach ([11, 12] as $grade) {
            $slots   = $this->service->getCandidateSlots($grade);
            $tuesdays = array_filter($slots, fn ($s) => $s['day'] === 'Tuesday');
            $this->assertEmpty($tuesdays,
                "G{$grade} Science Core must exclude Tuesday"
            );
        }
    }

    public function test_candidate_slots_have_required_keys(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $this->assertNotEmpty($slots);
        foreach ($slots as $slot) {
            $this->assertArrayHasKey('day',   $slot);
            $this->assertArrayHasKey('start', $slot);
            $this->assertArrayHasKey('end',   $slot);
            $this->assertArrayHasKey('label', $slot);
        }
    }

    public function test_candidate_slots_ordered_by_science_core_day_order(): void
    {
        $slots     = $this->service->getCandidateSlots(11);
        $dayOrder  = array_flip(SC::SCIENCE_CORE_DAYS); // Mon=0, Wed=1, Thu=2, Fri=3

        for ($i = 1; $i < count($slots); $i++) {
            $prevRank = $dayOrder[$slots[$i - 1]['day']];
            $currRank = $dayOrder[$slots[$i]['day']];
            $this->assertLessThanOrEqual($currRank, $prevRank,
                "Slot order: {$slots[$i-1]['day']} should come before {$slots[$i]['day']}"
            );
        }
    }

    public function test_g11_wednesday_has_4_candidate_slots(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $wed   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Wednesday'));
        $this->assertCount(4, $wed,
            'G11 Wednesday: 4 usable slots after Wellness and Activity lock filters'
        );
    }

    public function test_g11_wednesday_excludes_wellness_window(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');

        foreach ($wed as $slot) {
            $overlaps = SC::timesOverlap($slot['start'], $slot['end'], '09:50', '10:20');
            $this->assertFalse($overlaps,
                "G11 Wednesday slot {$slot['start']}–{$slot['end']} must not overlap Wellness"
            );
        }
    }

    public function test_g11_wednesday_excludes_slots_at_or_after_12_20(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');

        foreach ($wed as $slot) {
            $this->assertLessThan('12:20', $slot['start'],
                "G11 Wednesday slot {$slot['start']} must start before Activity lock 12:20"
            );
        }
    }

    public function test_g11_monday_has_6_candidate_slots(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $mon   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Monday'));
        $this->assertCount(6, $mon);
    }

    public function test_g11_thursday_has_8_candidate_slots(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $thu   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Thursday'));
        $this->assertCount(8, $thu);
    }

    public function test_g11_friday_has_8_candidate_slots(): void
    {
        $slots = $this->service->getCandidateSlots(11);
        $fri   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Friday'));
        $this->assertCount(8, $fri);
    }

    // =========================================================================
    // findParallelSlot
    // =========================================================================

    public function test_find_parallel_slot_returns_first_candidate(): void
    {
        $candidates = $this->service->getCandidateSlots(11);
        $first      = $this->service->findParallelSlot(11);

        $this->assertNotNull($first);
        $this->assertSame($candidates[0]['day'],   $first['day']);
        $this->assertSame($candidates[0]['start'], $first['start']);
    }

    public function test_find_parallel_slot_skips_booked_slots(): void
    {
        $candidates = $this->service->getCandidateSlots(11);
        $booked     = [
            ['day' => $candidates[0]['day'], 'start' => $candidates[0]['start']],
        ];

        $next = $this->service->findParallelSlot(11, $booked);

        $this->assertNotNull($next);
        $this->assertSame($candidates[1]['day'],   $next['day']);
        $this->assertSame($candidates[1]['start'], $next['start']);
    }

    public function test_find_parallel_slot_returns_null_when_all_booked(): void
    {
        $candidates = $this->service->getCandidateSlots(11);
        $booked     = array_map(
            fn ($s) => ['day' => $s['day'], 'start' => $s['start']],
            $candidates
        );

        $this->assertNull($this->service->findParallelSlot(11, $booked));
    }

    // =========================================================================
    // validateParallelPlacement
    // =========================================================================

    public function test_validate_passes_for_valid_thursday_slot_g11(): void
    {
        // Thursday 09:10–10:00 is a valid CLASS slot in TUEFRI_730_G11G12
        $result = $this->service->validateParallelPlacement(11, 'Thursday', '09:10', '10:00');
        $this->assertTrue($result['passes']);
        $this->assertNull($result['reason']);
    }

    public function test_validate_passes_for_valid_monday_slot_g11(): void
    {
        $result = $this->service->validateParallelPlacement(11, 'Monday', '08:50', '09:40');
        $this->assertTrue($result['passes']);
    }

    public function test_validate_fails_for_tuesday(): void
    {
        $result = $this->service->validateParallelPlacement(11, 'Tuesday', '07:30', '08:20');
        $this->assertFalse($result['passes']);
        $this->assertStringContainsString('H15', $result['reason']);
        $this->assertStringContainsString('Tuesday', $result['reason']);
    }

    public function test_validate_fails_for_wednesday_wellness_overlap_g11(): void
    {
        // 09:10–10:00 overlaps Wellness on Wednesday → H11
        $result = $this->service->validateParallelPlacement(11, 'Wednesday', '09:10', '10:00');
        $this->assertFalse($result['passes']);
        $this->assertStringContainsString('H11', $result['reason']);
    }

    public function test_validate_fails_for_wednesday_after_activity_lock_g11(): void
    {
        // 13:00–13:50 is after 12:20 activity lock on Wednesday for G11G12
        $result = $this->service->validateParallelPlacement(11, 'Wednesday', '13:00', '13:50');
        $this->assertFalse($result['passes']);
        $this->assertStringContainsString('H10', $result['reason']);
    }

    public function test_validate_fails_for_non_class_time(): void
    {
        // 07:30–08:00 on Monday is FLAG ceremony, not a CLASS slot
        $result = $this->service->validateParallelPlacement(11, 'Monday', '07:30', '08:00');
        $this->assertFalse($result['passes']);
    }

    public function test_validate_fails_for_arbitrary_time_not_in_timetable(): void
    {
        $result = $this->service->validateParallelPlacement(11, 'Thursday', '06:00', '07:00');
        $this->assertFalse($result['passes']);
    }

    // =========================================================================
    // getScienceCoreSubject
    // =========================================================================

    public function test_g11_venus_gets_physics_3(): void
    {
        $this->assertSame('Physics 3', $this->service->getScienceCoreSubject(11, 'Venus'));
    }

    public function test_g11_mars_gets_biology_3(): void
    {
        $this->assertSame('Biology 3', $this->service->getScienceCoreSubject(11, 'Mars'));
    }

    public function test_g11_mercury_gets_chemistry_3(): void
    {
        $this->assertSame('Chemistry 3', $this->service->getScienceCoreSubject(11, 'Mercury'));
    }

    public function test_g12_del_mundo_gets_physics_4(): void
    {
        $this->assertSame('Physics 4', $this->service->getScienceCoreSubject(12, 'Del Mundo'));
    }

    public function test_g12_orosa_gets_biology_4(): void
    {
        $this->assertSame('Biology 4', $this->service->getScienceCoreSubject(12, 'Orosa'));
    }

    public function test_g12_zara_gets_chemistry_4(): void
    {
        $this->assertSame('Chemistry 4', $this->service->getScienceCoreSubject(12, 'Zara'));
    }

    public function test_g11_subjects_are_all_distinct(): void
    {
        $subjects = array_map(
            fn ($s) => $this->service->getScienceCoreSubject(11, $s),
            SC::GRADE_SECTIONS[11]
        );
        $this->assertSame(count($subjects), count(array_unique($subjects)),
            'G11 Science Core sections must each have a distinct subject'
        );
    }

    public function test_invalid_grade_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getScienceCoreSubject(9, 'Calcium');
    }

    public function test_invalid_section_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getScienceCoreSubject(11, 'NonExistentSection');
    }

    // =========================================================================
    // buildPlacementPlan
    // =========================================================================

    public function test_build_plan_returns_correct_structure_for_g11(): void
    {
        $plan = $this->service->buildPlacementPlan(11, 'Thursday', '09:10', '10:00');

        $this->assertSame(11,          $plan['grade']);
        $this->assertSame('Thursday',  $plan['day']);
        $this->assertSame('09:10',     $plan['start']);
        $this->assertSame('10:00',     $plan['end']);
        $this->assertCount(3,           $plan['placements']);
    }

    public function test_build_plan_g11_has_all_sections(): void
    {
        $plan     = $this->service->buildPlacementPlan(11, 'Thursday', '09:10', '10:00');
        $sections = array_column($plan['placements'], 'section');

        $this->assertContains('Venus',   $sections);
        $this->assertContains('Mars',    $sections);
        $this->assertContains('Mercury', $sections);
    }

    public function test_build_plan_g11_has_distinct_subjects(): void
    {
        $plan     = $this->service->buildPlacementPlan(11, 'Thursday', '09:10', '10:00');
        $subjects = array_column($plan['placements'], 'subject');

        $this->assertSame(count($subjects), count(array_unique($subjects)),
            'Placement plan must assign distinct subjects to each section'
        );
    }

    public function test_build_plan_g12_maps_correct_subjects(): void
    {
        $plan = $this->service->buildPlacementPlan(12, 'Thursday', '09:10', '10:00');

        $subjectBySection = array_column($plan['placements'], 'subject', 'section');
        $this->assertSame('Physics 4',  $subjectBySection['Del Mundo']);
        $this->assertSame('Biology 4',  $subjectBySection['Orosa']);
        $this->assertSame('Chemistry 4', $subjectBySection['Zara']);
    }

    public function test_build_plan_throws_on_invalid_slot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->buildPlacementPlan(11, 'Tuesday', '07:30', '08:20');
    }

    // =========================================================================
    // validateH15 — compliance checks
    // =========================================================================

    public function test_h15_passes_when_all_same_time_and_distinct_subjects(): void
    {
        $placements = [
            ['section' => 'Venus',   'subject' => 'Physics 3',   'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
            ['section' => 'Mars',    'subject' => 'Biology 3',   'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
            ['section' => 'Mercury', 'subject' => 'Chemistry 3', 'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
        ];

        $result = $this->service->validateH15($placements);
        $this->assertTrue($result['passes']);
        $this->assertNull($result['reason']);
    }

    public function test_h15_fails_when_sections_have_different_days(): void
    {
        $placements = [
            ['section' => 'Venus',   'subject' => 'Physics 3',   'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
            ['section' => 'Mars',    'subject' => 'Biology 3',   'day' => 'Friday',   'start' => '09:10', 'end' => '10:00'],
            ['section' => 'Mercury', 'subject' => 'Chemistry 3', 'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
        ];

        $result = $this->service->validateH15($placements);
        $this->assertFalse($result['passes']);
        $this->assertStringContainsString('H15', $result['reason']);
    }

    public function test_h15_fails_when_sections_have_different_start_times(): void
    {
        $placements = [
            ['section' => 'Venus',   'subject' => 'Physics 3',   'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
            ['section' => 'Mars',    'subject' => 'Biology 3',   'day' => 'Thursday', 'start' => '10:00', 'end' => '10:50'],
            ['section' => 'Mercury', 'subject' => 'Chemistry 3', 'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
        ];

        $result = $this->service->validateH15($placements);
        $this->assertFalse($result['passes']);
        $this->assertStringContainsString('H15', $result['reason']);
    }

    public function test_h15_fails_when_subjects_are_duplicated(): void
    {
        $placements = [
            ['section' => 'Venus',   'subject' => 'Physics 3', 'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
            ['section' => 'Mars',    'subject' => 'Physics 3', 'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
            ['section' => 'Mercury', 'subject' => 'Biology 3', 'day' => 'Thursday', 'start' => '09:10', 'end' => '10:00'],
        ];

        $result = $this->service->validateH15($placements);
        $this->assertFalse($result['passes']);
        $this->assertStringContainsString('H15', $result['reason']);
    }

    public function test_h15_fails_for_empty_placements(): void
    {
        $result = $this->service->validateH15([]);
        $this->assertFalse($result['passes']);
    }

    // =========================================================================
    // autoPlace — convenience wrapper
    // =========================================================================

    public function test_auto_place_returns_valid_plan(): void
    {
        $plan = $this->service->autoPlace(11);

        $this->assertNotNull($plan);
        $this->assertSame(11, $plan['grade']);
        $this->assertCount(3, $plan['placements']);
    }

    public function test_auto_place_skips_booked_slots(): void
    {
        $firstSlot = $this->service->findParallelSlot(11);
        $booked    = [['day' => $firstSlot['day'], 'start' => $firstSlot['start']]];

        $plan = $this->service->autoPlace(11, $booked);

        $this->assertNotNull($plan);
        $this->assertNotSame(
            $firstSlot['day'] . '|' . $firstSlot['start'],
            $plan['day']      . '|' . $plan['start'],
            'autoPlace must skip the booked slot'
        );
    }

    public function test_auto_place_returns_null_when_all_slots_booked(): void
    {
        $candidates = $this->service->getCandidateSlots(11);
        $booked     = array_map(
            fn ($s) => ['day' => $s['day'], 'start' => $s['start']],
            $candidates
        );

        $this->assertNull($this->service->autoPlace(11, $booked));
    }
}
