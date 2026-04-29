<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\ElectiveAdvisoryPlacementService;
use App\Services\FacultyLoading\SchedulingConstants as SC;
use Tests\TestCase;

/**
 * Tests for ElectiveAdvisoryPlacementService — Phase 8.
 *
 * All tests are pure-logic (no DB).  Verifies:
 *   - Candidate elective slot discovery (counts, days, constraint compliance)
 *   - buildElectivePlan structure and parallel semantics
 *   - buildSectionElectivePlan + claimed-key skipping
 *   - validateElectivePlan
 *   - isInElectiveWindow
 *   - getAdvisorySlot (type per grade group)
 *   - getScaleAdvisingSlot
 *   - isAdvisoryBlock
 */
class ElectiveAdvisoryPlacementServiceTest extends TestCase
{
    private ElectiveAdvisoryPlacementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ElectiveAdvisoryPlacementService();
    }

    // =========================================================================
    // getCandidateElectiveSlots — counts
    // =========================================================================

    public function test_g7_has_no_elective_slots(): void
    {
        $this->assertEmpty($this->service->getCandidateElectiveSlots(7));
    }

    public function test_g8_has_no_elective_slots(): void
    {
        $this->assertEmpty($this->service->getCandidateElectiveSlots(8));
    }

    public function test_g9_has_no_elective_slots(): void
    {
        $this->assertEmpty($this->service->getCandidateElectiveSlots(9));
    }

    public function test_g10_has_2_elective_slots(): void
    {
        $this->assertCount(2, $this->service->getCandidateElectiveSlots(10));
    }

    public function test_g11_has_5_elective_slots(): void
    {
        $this->assertCount(5, $this->service->getCandidateElectiveSlots(11));
    }

    public function test_g12_has_5_elective_slots(): void
    {
        $this->assertCount(5, $this->service->getCandidateElectiveSlots(12));
    }

    // =========================================================================
    // getCandidateElectiveSlots — days
    // =========================================================================

    public function test_g10_elective_slots_are_on_wednesday_and_thursday(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(10);
        $days  = array_column($slots, 'day');

        $this->assertContains('Wednesday', $days, 'G10 must have a Wednesday elective slot');
        $this->assertContains('Thursday',  $days, 'G10 must have a Thursday elective slot');
        $this->assertNotContains('Monday',  $days);
        $this->assertNotContains('Tuesday', $days);
        $this->assertNotContains('Friday',  $days);
    }

    public function test_g11_elective_slots_span_tuesday_wednesday_thursday(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(11);
        $days  = array_column($slots, 'day');

        $this->assertContains('Tuesday',   $days);
        $this->assertContains('Wednesday', $days);
        $this->assertContains('Thursday',  $days);
        $this->assertNotContains('Monday', $days);
        $this->assertNotContains('Friday', $days);
    }

    public function test_g11_has_2_tuesday_elective_slots(): void
    {
        $slots   = $this->service->getCandidateElectiveSlots(11);
        $tuesday = array_filter($slots, fn ($s) => $s['day'] === 'Tuesday');
        $this->assertCount(2, $tuesday);
    }

    public function test_g11_has_1_wednesday_elective_slot(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(11);
        $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');
        $this->assertCount(1, $wed);
    }

    public function test_g11_has_2_thursday_elective_slots(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(11);
        $thu   = array_filter($slots, fn ($s) => $s['day'] === 'Thursday');
        $this->assertCount(2, $thu);
    }

    // =========================================================================
    // getCandidateElectiveSlots — exact slot times
    // =========================================================================

    public function test_g10_wednesday_elective_slot_is_14_10_to_15_00(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(10);
        $wed   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Wednesday'));

        $this->assertCount(1, $wed);
        $this->assertSame('14:10', $wed[0]['start']);
        $this->assertSame('15:00', $wed[0]['end']);
    }

    public function test_g10_thursday_elective_slot_is_14_10_to_15_00(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(10);
        $thu   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Thursday'));

        $this->assertCount(1, $thu);
        $this->assertSame('14:10', $thu[0]['start']);
        $this->assertSame('15:00', $thu[0]['end']);
    }

    public function test_g11_wednesday_elective_slot_is_10_20_to_11_10(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(11);
        $wed   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Wednesday'));

        $this->assertCount(1, $wed);
        $this->assertSame('10:20', $wed[0]['start']);
        $this->assertSame('11:10', $wed[0]['end']);
    }

    // =========================================================================
    // getCandidateElectiveSlots — hard constraint compliance
    // =========================================================================

    public function test_g10_elective_slots_all_pass_hard_constraints(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(10);
        foreach ($slots as $slot) {
            $check = \App\Services\FacultyLoading\HardConstraintChecker::checkSpecialDayLocks(
                10, $slot['day'], $slot['start'], $slot['end']
            );
            $this->assertTrue($check['passes'],
                "G10 elective slot {$slot['day']} {$slot['start']}: {$check['reason']}"
            );
        }
    }

    public function test_g11_elective_slots_all_pass_hard_constraints(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(11);
        foreach ($slots as $slot) {
            $check = \App\Services\FacultyLoading\HardConstraintChecker::checkSpecialDayLocks(
                11, $slot['day'], $slot['start'], $slot['end']
            );
            $this->assertTrue($check['passes'],
                "G11 elective slot {$slot['day']} {$slot['start']}: {$check['reason']}"
            );
        }
    }

    public function test_g11_wednesday_slot_does_not_overlap_wellness(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(11);
        $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');

        foreach ($wed as $slot) {
            $overlaps = SC::timesOverlap($slot['start'], $slot['end'], '09:50', '10:20');
            $this->assertFalse($overlaps,
                "G11 Wednesday elective slot {$slot['start']}–{$slot['end']} overlaps Wellness"
            );
        }
    }

    public function test_g11_wednesday_slot_is_before_activity_lock(): void
    {
        $slots = $this->service->getCandidateElectiveSlots(11);
        $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');

        foreach ($wed as $slot) {
            $this->assertLessThan('12:20', $slot['start'],
                "G11 Wednesday elective must start before 12:20 activity lock"
            );
        }
    }

    // =========================================================================
    // getCandidateElectiveSlots — slot structure
    // =========================================================================

    public function test_elective_slots_have_required_keys(): void
    {
        foreach ([10, 11, 12] as $grade) {
            foreach ($this->service->getCandidateElectiveSlots($grade) as $slot) {
                $this->assertArrayHasKey('day',   $slot, "G{$grade}: slot missing 'day'");
                $this->assertArrayHasKey('start', $slot, "G{$grade}: slot missing 'start'");
                $this->assertArrayHasKey('end',   $slot, "G{$grade}: slot missing 'end'");
                $this->assertArrayHasKey('label', $slot, "G{$grade}: slot missing 'label'");
            }
        }
    }

    public function test_no_duplicate_slots_in_candidate_list(): void
    {
        foreach ([10, 11, 12] as $grade) {
            $slots = $this->service->getCandidateElectiveSlots($grade);
            $keys  = array_map(fn ($s) => $s['day'] . '|' . $s['start'], $slots);
            $this->assertSame(count($keys), count(array_unique($keys)),
                "G{$grade}: duplicate slots in candidate list"
            );
        }
    }

    // =========================================================================
    // getElectiveCapacity
    // =========================================================================

    public function test_elective_capacity_is_0_for_g7(): void
    {
        $this->assertSame(0, $this->service->getElectiveCapacity(7));
    }

    public function test_elective_capacity_is_2_for_g10(): void
    {
        $this->assertSame(2, $this->service->getElectiveCapacity(10));
    }

    public function test_elective_capacity_is_5_for_g11(): void
    {
        $this->assertSame(5, $this->service->getElectiveCapacity(11));
    }

    public function test_elective_capacity_is_5_for_g12(): void
    {
        $this->assertSame(5, $this->service->getElectiveCapacity(12));
    }

    // =========================================================================
    // buildElectivePlan — structure
    // =========================================================================

    public function test_build_elective_plan_returns_top_level_keys(): void
    {
        $plan = $this->service->buildElectivePlan(10, [
            ['name' => 'STE Elective', 'sessions_per_week' => 2],
        ]);

        $this->assertArrayHasKey('grade',          $plan);
        $this->assertArrayHasKey('total_sessions', $plan);
        $this->assertArrayHasKey('unfilled',       $plan);
        $this->assertArrayHasKey('sections',       $plan);
        $this->assertSame(10, $plan['grade']);
        $this->assertSame(2,  $plan['total_sessions']);
    }

    public function test_build_elective_plan_has_all_g10_sections(): void
    {
        $plan = $this->service->buildElectivePlan(10, [
            ['name' => 'STE Elective', 'sessions_per_week' => 2],
        ]);

        foreach (SC::GRADE_SECTIONS[10] as $name) {
            $this->assertArrayHasKey($name, $plan['sections']);
        }
    }

    public function test_build_elective_plan_has_all_g11_sections(): void
    {
        $plan = $this->service->buildElectivePlan(11, [
            ['name' => 'Elective', 'sessions_per_week' => 3],
        ]);

        foreach (SC::GRADE_SECTIONS[11] as $name) {
            $this->assertArrayHasKey($name, $plan['sections']);
        }
    }

    public function test_build_elective_plan_each_section_has_correct_session_count(): void
    {
        $plan = $this->service->buildElectivePlan(10, [
            ['name' => 'STE Elective', 'sessions_per_week' => 2],
        ]);

        foreach ($plan['sections'] as $sectionName => $placements) {
            $this->assertCount(2, $placements, "{$sectionName}: expected 2 elective placements");
        }
    }

    public function test_build_elective_plan_is_parallel_all_sections_same_slots(): void
    {
        $plan = $this->service->buildElectivePlan(11, [
            ['name' => 'STEM Elective', 'sessions_per_week' => 5],
        ]);

        $allSlotKeys = [];
        foreach ($plan['sections'] as $sectionName => $placements) {
            $keys = array_map(fn ($p) => $p['day'] . '|' . $p['start'], $placements);
            $allSlotKeys[] = $keys;
        }

        // All sections must have identical slot sets (parallel placement)
        $first = $allSlotKeys[0];
        foreach (array_slice($allSlotKeys, 1) as $otherKeys) {
            $this->assertSame($first, $otherKeys,
                'G11 sections must have identical elective slots (parallel placement)'
            );
        }
    }

    // =========================================================================
    // buildElectivePlan — unfilled tracking
    // =========================================================================

    public function test_unfilled_empty_when_within_capacity(): void
    {
        // G10 capacity = 2 — request exactly 2 sessions
        $plan = $this->service->buildElectivePlan(10, [
            ['name' => 'STE', 'sessions_per_week' => 2],
        ]);

        $this->assertEmpty($plan['unfilled']);
    }

    public function test_unfilled_populated_when_exceeding_capacity(): void
    {
        // G10 capacity = 2 — request 3 sessions
        $plan = $this->service->buildElectivePlan(10, [
            ['name' => 'STE', 'sessions_per_week' => 3],
        ]);

        $this->assertCount(1, $plan['unfilled']);
        $this->assertSame('STE', $plan['unfilled'][0]['subject']);
        $this->assertSame(3,     $plan['unfilled'][0]['session']);
    }

    public function test_unfilled_is_empty_for_g7_when_no_subjects_given(): void
    {
        $plan = $this->service->buildElectivePlan(7, []);
        $this->assertEmpty($plan['unfilled']);
    }

    // =========================================================================
    // buildElectivePlan — validateElectivePlan integration
    // =========================================================================

    public function test_g10_plan_passes_validate(): void
    {
        $plan   = $this->service->buildElectivePlan(10, [
            ['name' => 'STE Elective', 'sessions_per_week' => 2],
        ]);
        $result = $this->service->validateElectivePlan($plan);

        $this->assertTrue($result['passes'],
            'G10 elective plan: ' . implode('; ', $result['violations'])
        );
    }

    public function test_g11_plan_passes_validate(): void
    {
        $plan   = $this->service->buildElectivePlan(11, [
            ['name' => 'STEM Elective', 'sessions_per_week' => 5],
        ]);
        $result = $this->service->validateElectivePlan($plan);

        $this->assertTrue($result['passes'],
            'G11 elective plan: ' . implode('; ', $result['violations'])
        );
    }

    // =========================================================================
    // buildSectionElectivePlan
    // =========================================================================

    public function test_build_section_elective_plan_returns_correct_count(): void
    {
        $result = $this->service->buildSectionElectivePlan(10, [
            ['name' => 'STE', 'sessions_per_week' => 2],
        ]);
        $this->assertCount(2, $result);
    }

    public function test_build_section_elective_plan_has_required_keys(): void
    {
        $result = $this->service->buildSectionElectivePlan(11, [
            ['name' => 'Elective', 'sessions_per_week' => 1],
        ]);
        $this->assertCount(1, $result);

        $p = $result[0];
        $this->assertArrayHasKey('subject', $p);
        $this->assertArrayHasKey('session', $p);
        $this->assertArrayHasKey('day',     $p);
        $this->assertArrayHasKey('start',   $p);
        $this->assertArrayHasKey('end',     $p);
    }

    public function test_build_section_elective_plan_skips_claimed_keys(): void
    {
        $slots    = $this->service->getCandidateElectiveSlots(11);
        $firstKey = $slots[0]['day'] . '|' . $slots[0]['start'];
        $secondKey = $slots[1]['day'] . '|' . $slots[1]['start'];

        $result = $this->service->buildSectionElectivePlan(11, [
            ['name' => 'Elective', 'sessions_per_week' => 1],
        ], [$firstKey]);

        $this->assertCount(1, $result);
        $placed = $result[0]['day'] . '|' . $result[0]['start'];
        $this->assertSame($secondKey, $placed,
            'buildSectionElectivePlan must skip the pre-claimed first slot'
        );
    }

    public function test_build_section_elective_plan_returns_empty_for_empty_subjects(): void
    {
        $this->assertEmpty($this->service->buildSectionElectivePlan(10, []));
    }

    public function test_build_section_elective_plan_returns_empty_for_grades_without_windows(): void
    {
        $this->assertEmpty($this->service->buildSectionElectivePlan(7, [
            ['name' => 'Elective', 'sessions_per_week' => 2],
        ]));
    }

    // =========================================================================
    // validateElectivePlan — failure cases
    // =========================================================================

    public function test_validate_fails_when_slot_not_in_window(): void
    {
        $plan = [
            'grade'    => 10,
            'sections' => [
                'Electron' => [[
                    'subject' => 'STE',
                    'session' => 1,
                    'day'     => 'Monday',
                    'start'   => '10:00',
                    'end'     => '10:50',
                ]],
            ],
        ];

        $result = $this->service->validateElectivePlan($plan);
        $this->assertFalse($result['passes']);
        $this->assertNotEmpty($result['violations']);
    }

    public function test_validate_fails_for_duplicate_slot_in_section(): void
    {
        $slot = [
            'subject' => 'STE',
            'session' => 1,
            'day'     => 'Wednesday',
            'start'   => '14:10',
            'end'     => '15:00',
        ];

        $plan = [
            'grade'    => 10,
            'sections' => [
                'Electron' => [$slot, array_merge($slot, ['session' => 2])],
            ],
        ];

        $result = $this->service->validateElectivePlan($plan);
        $this->assertFalse($result['passes']);
    }

    // =========================================================================
    // isInElectiveWindow
    // =========================================================================

    public function test_g10_wednesday_slot_is_in_elective_window(): void
    {
        $this->assertTrue(
            $this->service->isInElectiveWindow(10, 'Wednesday', '14:10', '15:00')
        );
    }

    public function test_g10_thursday_slot_is_in_elective_window(): void
    {
        $this->assertTrue(
            $this->service->isInElectiveWindow(10, 'Thursday', '14:10', '15:00')
        );
    }

    public function test_g10_monday_slot_is_not_in_elective_window(): void
    {
        $this->assertFalse(
            $this->service->isInElectiveWindow(10, 'Monday', '10:00', '10:50')
        );
    }

    public function test_g11_tuesday_slot_is_in_elective_window(): void
    {
        $this->assertTrue(
            $this->service->isInElectiveWindow(11, 'Tuesday', '13:00', '13:50')
        );
    }

    public function test_g11_wednesday_slot_is_in_elective_window(): void
    {
        $this->assertTrue(
            $this->service->isInElectiveWindow(11, 'Wednesday', '10:20', '11:10')
        );
    }

    public function test_g11_thursday_slot_is_in_elective_window(): void
    {
        $this->assertTrue(
            $this->service->isInElectiveWindow(11, 'Thursday', '13:50', '14:40')
        );
    }

    public function test_g7_any_slot_is_not_in_elective_window(): void
    {
        $this->assertFalse(
            $this->service->isInElectiveWindow(7, 'Wednesday', '14:10', '15:00')
        );
    }

    public function test_g10_tuesday_slot_is_not_in_elective_window(): void
    {
        $this->assertFalse(
            $this->service->isInElectiveWindow(10, 'Tuesday', '07:30', '08:20')
        );
    }

    // =========================================================================
    // getAdvisorySlot
    // =========================================================================

    public function test_advisory_slot_is_monday_for_all_grades(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            $slot = $this->service->getAdvisorySlot($grade);
            $this->assertSame('Monday', $slot['day'], "G{$grade} advisory must be Monday");
        }
    }

    public function test_advisory_slot_is_08_00_to_08_50_for_all_grades(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            $slot = $this->service->getAdvisorySlot($grade);
            $this->assertSame('08:00', $slot['start'], "G{$grade} advisory start");
            $this->assertSame('08:50', $slot['end'],   "G{$grade} advisory end");
        }
    }

    public function test_advisory_slot_type_is_homeroom_for_g7_to_g10(): void
    {
        foreach ([7, 8, 9, 10] as $grade) {
            $slot = $this->service->getAdvisorySlot($grade);
            $this->assertSame('HOMEROOM', $slot['type'], "G{$grade} must be HOMEROOM type");
        }
    }

    public function test_advisory_slot_type_is_advising_for_g11_and_g12(): void
    {
        foreach ([11, 12] as $grade) {
            $slot = $this->service->getAdvisorySlot($grade);
            $this->assertSame('ADVISING', $slot['type'], "G{$grade} must be ADVISING type");
        }
    }

    public function test_advisory_slot_has_label(): void
    {
        $g7  = $this->service->getAdvisorySlot(7);
        $g11 = $this->service->getAdvisorySlot(11);

        $this->assertArrayHasKey('label', $g7);
        $this->assertArrayHasKey('label', $g11);
        $this->assertNotEmpty($g7['label']);
        $this->assertNotEmpty($g11['label']);
    }

    // =========================================================================
    // getScaleAdvisingSlot
    // =========================================================================

    public function test_scale_advising_slot_is_tuesday(): void
    {
        $slot = $this->service->getScaleAdvisingSlot();
        $this->assertSame('Tuesday', $slot['day']);
    }

    public function test_scale_advising_slot_is_11_10_to_12_00(): void
    {
        $slot = $this->service->getScaleAdvisingSlot();
        $this->assertSame('11:10', $slot['start']);
        $this->assertSame('12:00', $slot['end']);
    }

    // =========================================================================
    // isAdvisoryBlock
    // =========================================================================

    public function test_monday_homeroom_is_advisory_block_for_all_grades(): void
    {
        foreach ([7, 9, 11] as $grade) {
            $this->assertTrue(
                $this->service->isAdvisoryBlock($grade, 'Monday', '08:00', '08:50'),
                "G{$grade} Monday 08:00–08:50 must be advisory block"
            );
        }
    }

    public function test_scale_advising_is_advisory_block_for_g11_and_g12(): void
    {
        $this->assertTrue($this->service->isAdvisoryBlock(11, 'Tuesday', '11:10', '12:00'));
        $this->assertTrue($this->service->isAdvisoryBlock(12, 'Tuesday', '11:10', '12:00'));
    }

    public function test_scale_advising_is_not_advisory_block_for_g7(): void
    {
        $this->assertFalse(
            $this->service->isAdvisoryBlock(7, 'Tuesday', '11:10', '12:00'),
            'SCALE Advising only applies to G11/G12'
        );
    }

    public function test_regular_class_slot_is_not_advisory_block(): void
    {
        $this->assertFalse(
            $this->service->isAdvisoryBlock(7, 'Tuesday', '07:30', '08:20')
        );
        $this->assertFalse(
            $this->service->isAdvisoryBlock(11, 'Thursday', '13:50', '14:40')
        );
    }

    public function test_monday_non_homeroom_time_is_not_advisory_block(): void
    {
        $this->assertFalse(
            $this->service->isAdvisoryBlock(7, 'Monday', '10:00', '10:50')
        );
    }
}
