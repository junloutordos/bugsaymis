<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\CoreSubjectPlacementService;
use App\Services\FacultyLoading\RotationGridBuilder;
use App\Services\FacultyLoading\SchedulingConstants as SC;
use Tests\TestCase;

/**
 * Tests for CoreSubjectPlacementService — Phase 7.
 *
 * All tests are pure-logic (no DB) and verify:
 *   - Placement plan structure and dimensions
 *   - No duplicate (day, start) within any section
 *   - Anti-collision property across sections
 *   - Hard constraint compliance for every placed slot
 *   - Unfilled tracking when slot capacity is exceeded
 *   - buildSectionPlan + claimed-key skipping
 *   - Invalid grade rejection
 */
class CoreSubjectPlacementServiceTest extends TestCase
{
    private CoreSubjectPlacementService $service;

    /** Typical G7 core subject load (total sessions = 28 = exactly T) */
    private array $g7Subjects = [
        ['name' => 'Filipino',       'sessions_per_week' => 5],
        ['name' => 'English',        'sessions_per_week' => 5],
        ['name' => 'Mathematics',    'sessions_per_week' => 5],
        ['name' => 'Science',        'sessions_per_week' => 5],
        ['name' => 'Social Studies', 'sessions_per_week' => 3],
        ['name' => 'MAPEH',          'sessions_per_week' => 3],
        ['name' => 'TLE',            'sessions_per_week' => 2],
    ];  // 5+5+5+5+3+3+2 = 28

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CoreSubjectPlacementService(new RotationGridBuilder());
    }

    // =========================================================================
    // buildGradePlan — structure
    // =========================================================================

    public function test_build_grade_plan_returns_top_level_keys(): void
    {
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        $this->assertArrayHasKey('grade',          $plan);
        $this->assertArrayHasKey('total_sessions', $plan);
        $this->assertArrayHasKey('unfilled',       $plan);
        $this->assertArrayHasKey('sections',       $plan);
        $this->assertSame(7, $plan['grade']);
        $this->assertSame(28, $plan['total_sessions']);
    }

    public function test_build_grade_plan_has_all_four_sections_for_g7(): void
    {
        $plan     = $this->service->buildGradePlan(7, $this->g7Subjects);
        $sections = array_keys($plan['sections']);

        $this->assertCount(4, $sections);
        foreach (SC::GRADE_SECTIONS[7] as $name) {
            $this->assertArrayHasKey($name, $plan['sections']);
        }
    }

    public function test_build_grade_plan_each_section_has_total_sessions_placements(): void
    {
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        foreach ($plan['sections'] as $sectionName => $placements) {
            $this->assertCount(28, $placements,
                "{$sectionName} must have 28 placements (one per session)"
            );
        }
    }

    public function test_each_placement_has_required_keys(): void
    {
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        foreach ($plan['sections'] as $sectionName => $placements) {
            foreach ($placements as $p) {
                $this->assertArrayHasKey('subject',    $p, "{$sectionName} placement missing 'subject'");
                $this->assertArrayHasKey('session',    $p, "{$sectionName} placement missing 'session'");
                $this->assertArrayHasKey('day',        $p, "{$sectionName} placement missing 'day'");
                $this->assertArrayHasKey('start',      $p, "{$sectionName} placement missing 'start'");
                $this->assertArrayHasKey('end',        $p, "{$sectionName} placement missing 'end'");
                $this->assertArrayHasKey('slot_index', $p, "{$sectionName} placement missing 'slot_index'");
            }
        }
    }

    // =========================================================================
    // buildGradePlan — no duplicate slots within a section
    // =========================================================================

    public function test_no_duplicate_slots_within_section_for_g7(): void
    {
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        foreach ($plan['sections'] as $sectionName => $placements) {
            $keys = array_map(fn ($p) => $p['day'] . '|' . $p['start'], $placements);
            $this->assertCount(
                count($keys),
                array_unique($keys),
                "{$sectionName}: duplicate (day, start) found"
            );
        }
    }

    public function test_no_duplicate_slots_within_section_for_g8(): void
    {
        $subjects = [
            ['name' => 'English', 'sessions_per_week' => 7],
            ['name' => 'Math',    'sessions_per_week' => 7],
            ['name' => 'Science', 'sessions_per_week' => 7],
            ['name' => 'AP',      'sessions_per_week' => 7],
        ];  // 28 = T for G8

        $plan = $this->service->buildGradePlan(8, $subjects);

        foreach ($plan['sections'] as $sectionName => $placements) {
            $keys = array_map(fn ($p) => $p['day'] . '|' . $p['start'], $placements);
            $this->assertSame(count($keys), count(array_unique($keys)),
                "{$sectionName}: duplicate slot found"
            );
        }
    }

    public function test_no_duplicate_slots_within_section_for_g9(): void
    {
        $subjects = [
            ['name' => 'English', 'sessions_per_week' => 9],
            ['name' => 'Math',    'sessions_per_week' => 9],
            ['name' => 'Science', 'sessions_per_week' => 9],
            ['name' => 'AP',      'sessions_per_week' => 9],
        ];  // 36 = T for G9

        $plan = $this->service->buildGradePlan(9, $subjects);

        foreach ($plan['sections'] as $sectionName => $placements) {
            $keys = array_map(fn ($p) => $p['day'] . '|' . $p['start'], $placements);
            $this->assertSame(count($keys), count(array_unique($keys)));
        }
    }

    // =========================================================================
    // buildGradePlan — subject / session metadata correct
    // =========================================================================

    public function test_subject_names_are_correctly_assigned(): void
    {
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        // First 5 placements for every section must be 'Filipino'
        foreach ($plan['sections'] as $sectionName => $placements) {
            for ($i = 0; $i < 5; $i++) {
                $this->assertSame('Filipino', $placements[$i]['subject'],
                    "{$sectionName}: placement {$i} should be Filipino"
                );
            }
        }
    }

    public function test_session_numbers_start_at_1_and_increment_per_subject(): void
    {
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        // Placements 0–4 are Filipino sessions 1–5
        $first = $plan['sections'][SC::GRADE_SECTIONS[7][0]];
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($i + 1, $first[$i]['session']);
        }
        // Placements 5–9 are English sessions 1–5
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('English', $first[5 + $i]['subject']);
            $this->assertSame($i + 1,   $first[5 + $i]['session']);
        }
    }

    // =========================================================================
    // buildGradePlan — anti-collision across sections
    // =========================================================================

    public function test_anti_collision_passes_for_g7(): void
    {
        $plan   = $this->service->buildGradePlan(7, $this->g7Subjects);
        $result = $this->service->checkAntiCollision($plan);

        $this->assertTrue($result['passes'],
            'G7 anti-collision failed: ' . implode('; ', $result['collisions'])
        );
    }

    public function test_anti_collision_passes_for_g8(): void
    {
        $subjects = [
            ['name' => 'A', 'sessions_per_week' => 7],
            ['name' => 'B', 'sessions_per_week' => 7],
            ['name' => 'C', 'sessions_per_week' => 7],
            ['name' => 'D', 'sessions_per_week' => 7],
        ];
        $plan   = $this->service->buildGradePlan(8, $subjects);
        $result = $this->service->checkAntiCollision($plan);

        $this->assertTrue($result['passes'],
            'G8 anti-collision failed: ' . implode('; ', $result['collisions'])
        );
    }

    public function test_anti_collision_passes_for_g9(): void
    {
        $subjects = [
            ['name' => 'A', 'sessions_per_week' => 9],
            ['name' => 'B', 'sessions_per_week' => 9],
            ['name' => 'C', 'sessions_per_week' => 9],
            ['name' => 'D', 'sessions_per_week' => 9],
        ];
        $plan   = $this->service->buildGradePlan(9, $subjects);
        $result = $this->service->checkAntiCollision($plan);

        $this->assertTrue($result['passes'],
            'G9 anti-collision failed: ' . implode('; ', $result['collisions'])
        );
    }

    public function test_anti_collision_passes_in_safe_range_for_g10(): void
    {
        // G10 T=37, step=9 → safe range is 9 sessions per section (36 positions)
        $subjects = [
            ['name' => 'A', 'sessions_per_week' => 9],
            ['name' => 'B', 'sessions_per_week' => 9],
            ['name' => 'C', 'sessions_per_week' => 9],
            ['name' => 'D', 'sessions_per_week' => 9],
        ];  // 36 sessions — within safe range
        $plan   = $this->service->buildGradePlan(10, $subjects);
        $result = $this->service->checkAntiCollision($plan);

        $this->assertTrue($result['passes'],
            'G10 anti-collision failed (safe range): ' . implode('; ', $result['collisions'])
        );
    }

    // =========================================================================
    // buildGradePlan — hard constraint compliance
    // =========================================================================

    public function test_validate_plan_passes_for_g7_full_load(): void
    {
        $plan   = $this->service->buildGradePlan(7, $this->g7Subjects);
        $result = $this->service->validatePlan($plan);

        $this->assertTrue($result['passes'],
            'G7 validatePlan failed: ' . implode('; ', $result['violations'])
        );
    }

    public function test_validate_plan_passes_for_g8(): void
    {
        $subjects = [
            ['name' => 'English', 'sessions_per_week' => 7],
            ['name' => 'Math',    'sessions_per_week' => 7],
            ['name' => 'Science', 'sessions_per_week' => 7],
            ['name' => 'AP',      'sessions_per_week' => 7],
        ];
        $plan   = $this->service->buildGradePlan(8, $subjects);
        $result = $this->service->validatePlan($plan);

        $this->assertTrue($result['passes'],
            'G8 validatePlan failed: ' . implode('; ', $result['violations'])
        );
    }

    public function test_validate_plan_passes_for_g9(): void
    {
        $subjects = [
            ['name' => 'English', 'sessions_per_week' => 9],
            ['name' => 'Math',    'sessions_per_week' => 9],
            ['name' => 'Science', 'sessions_per_week' => 9],
            ['name' => 'AP',      'sessions_per_week' => 9],
        ];
        $plan   = $this->service->buildGradePlan(9, $subjects);
        $result = $this->service->validatePlan($plan);

        $this->assertTrue($result['passes'],
            'G9 validatePlan failed: ' . implode('; ', $result['violations'])
        );
    }

    public function test_no_friday_slots_in_g7_plan(): void
    {
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        foreach ($plan['sections'] as $sectionName => $placements) {
            $fridays = array_filter($placements, fn ($p) => $p['day'] === 'Friday');
            $this->assertEmpty($fridays,
                "{$sectionName}: G7 must have no Friday placements (ILA day)"
            );
        }
    }

    public function test_no_monday_dead_zone_in_g7_plan(): void
    {
        // G7/G8 Monday 08:50–09:40 is a dead zone (H9)
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);

        foreach ($plan['sections'] as $sectionName => $placements) {
            $monSlots = array_filter($placements, fn ($p) => $p['day'] === 'Monday');
            foreach ($monSlots as $p) {
                $overlaps = SC::timesOverlap($p['start'], $p['end'], '08:50', '09:40');
                $this->assertFalse($overlaps,
                    "{$sectionName}: Monday slot {$p['start']}–{$p['end']} overlaps dead zone"
                );
            }
        }
    }

    public function test_no_wednesday_wellness_overlap_in_any_grade(): void
    {
        foreach ([7, 9] as $grade) {
            $plan = $this->service->buildGradePlan($grade, [
                ['name' => 'English', 'sessions_per_week' => 5],
            ]);
            foreach ($plan['sections'] as $sectionName => $placements) {
                $wedSlots = array_filter($placements, fn ($p) => $p['day'] === 'Wednesday');
                foreach ($wedSlots as $p) {
                    $overlaps = SC::timesOverlap($p['start'], $p['end'], '09:50', '10:20');
                    $this->assertFalse($overlaps,
                        "G{$grade} {$sectionName}: Wednesday slot {$p['start']}–{$p['end']} "
                        . "overlaps Wellness window"
                    );
                }
            }
        }
    }

    // =========================================================================
    // buildGradePlan — unfilled tracking
    // =========================================================================

    public function test_unfilled_is_empty_when_sessions_fit_in_capacity(): void
    {
        // 28 sessions = exactly T for G7 — should fill perfectly
        $plan = $this->service->buildGradePlan(7, $this->g7Subjects);
        $this->assertEmpty($plan['unfilled'],
            'G7: 28 sessions should fit exactly into T=28 slots, unfilled must be empty'
        );
    }

    public function test_unfilled_is_populated_when_sessions_exceed_capacity(): void
    {
        // Request more sessions than available slots (T=28 for G7)
        $subjects = [
            ['name' => 'Overflow', 'sessions_per_week' => 30],
        ];
        $plan = $this->service->buildGradePlan(7, $subjects);

        // 2 sessions per section cannot fit (28 slots fully used for first 28 sessions)
        $this->assertNotEmpty($plan['unfilled'],
            'G7: 30 sessions should overflow T=28 capacity'
        );

        // Each section contributes 2 overflow entries
        $this->assertCount(4 * 2, $plan['unfilled']);
    }

    public function test_unfilled_contains_correct_subject_and_session(): void
    {
        $subjects = [['name' => 'TooMany', 'sessions_per_week' => 29]];
        $plan     = $this->service->buildGradePlan(7, $subjects);

        // First unfilled for any section should be session 29
        $this->assertNotEmpty($plan['unfilled']);
        $first = $plan['unfilled'][0];
        $this->assertSame('TooMany', $first['subject']);
        $this->assertSame(29,        $first['session']);
    }

    // =========================================================================
    // getCapacity
    // =========================================================================

    public function test_get_capacity_returns_28_for_g7(): void
    {
        $this->assertSame(28, $this->service->getCapacity(7));
    }

    public function test_get_capacity_returns_28_for_g8(): void
    {
        $this->assertSame(28, $this->service->getCapacity(8));
    }

    public function test_get_capacity_returns_36_for_g9(): void
    {
        $this->assertSame(36, $this->service->getCapacity(9));
    }

    public function test_get_capacity_returns_37_for_g10(): void
    {
        $this->assertSame(37, $this->service->getCapacity(10));
    }

    // =========================================================================
    // buildSectionPlan
    // =========================================================================

    public function test_build_section_plan_returns_correct_count(): void
    {
        $subjects = [['name' => 'English', 'sessions_per_week' => 5]];
        $result   = $this->service->buildSectionPlan(7, 0, $subjects);
        $this->assertCount(5, $result);
    }

    public function test_build_section_plan_has_required_keys(): void
    {
        $subjects = [['name' => 'English', 'sessions_per_week' => 1]];
        $result   = $this->service->buildSectionPlan(7, 0, $subjects);
        $this->assertCount(1, $result);

        $p = $result[0];
        $this->assertArrayHasKey('subject',    $p);
        $this->assertArrayHasKey('session',    $p);
        $this->assertArrayHasKey('day',        $p);
        $this->assertArrayHasKey('start',      $p);
        $this->assertArrayHasKey('end',        $p);
        $this->assertArrayHasKey('slot_index', $p);
    }

    public function test_build_section_plan_skips_pre_claimed_keys(): void
    {
        // Build the unconstrained plan first to find slot 0's key
        $builder  = new RotationGridBuilder();
        $grid     = $builder->buildGrid(7);
        $slot0    = $grid[0][0];
        $key0     = $slot0['day'] . '|' . $slot0['start'];

        // Claim the first slot; first placement should skip to slot 1
        $subjects = [['name' => 'English', 'sessions_per_week' => 1]];
        $result   = $this->service->buildSectionPlan(7, 0, $subjects, [$key0]);

        $this->assertCount(1, $result);
        $placed = $result[0]['day'] . '|' . $result[0]['start'];
        $this->assertNotSame($key0, $placed,
            'buildSectionPlan must skip the pre-claimed key'
        );

        // The placed slot should match grid[0][1]
        $slot1 = $grid[0][1];
        $key1  = $slot1['day'] . '|' . $slot1['start'];
        $this->assertSame($key1, $placed);
    }

    public function test_build_section_plan_section_0_first_slot_matches_rotation_grid(): void
    {
        $builder = new RotationGridBuilder();
        $grid    = $builder->buildGrid(7);
        $slot0   = $grid[0][0];

        $subjects = [['name' => 'Filipino', 'sessions_per_week' => 1]];
        $result   = $this->service->buildSectionPlan(7, 0, $subjects);

        $this->assertSame($slot0['day'],   $result[0]['day']);
        $this->assertSame($slot0['start'], $result[0]['start']);
    }

    public function test_build_section_plan_sections_0_and_1_get_different_first_slot(): void
    {
        $subjects = [['name' => 'English', 'sessions_per_week' => 1]];

        $r0   = $this->service->buildSectionPlan(7, 0, $subjects);
        $r1   = $this->service->buildSectionPlan(7, 1, $subjects);
        $key0 = $r0[0]['day'] . '|' . $r0[0]['start'];
        $key1 = $r1[0]['day'] . '|' . $r1[0]['start'];

        $this->assertNotSame($key0, $key1,
            'Sections 0 and 1 must have different first slots (rotation property)'
        );
    }

    public function test_build_section_plan_returns_empty_for_empty_subjects(): void
    {
        $this->assertEmpty($this->service->buildSectionPlan(7, 0, []));
    }

    // =========================================================================
    // checkAntiCollision — rejects collisions
    // =========================================================================

    public function test_check_anti_collision_fails_for_crafted_collision(): void
    {
        $plan = [
            'grade'    => 7,
            'sections' => [
                'Aquamarine' => [['subject' => 'A', 'session' => 1, 'day' => 'Monday', 'start' => '10:00', 'end' => '10:50', 'slot_index' => 0]],
                'Opal'       => [['subject' => 'A', 'session' => 1, 'day' => 'Monday', 'start' => '10:00', 'end' => '10:50', 'slot_index' => 0]],
            ],
        ];

        $result = $this->service->checkAntiCollision($plan);
        $this->assertFalse($result['passes']);
        $this->assertNotEmpty($result['collisions']);
    }

    public function test_check_anti_collision_passes_for_distinct_slots(): void
    {
        $plan = [
            'grade'    => 7,
            'sections' => [
                'Aquamarine' => [['subject' => 'A', 'session' => 1, 'day' => 'Monday',  'start' => '10:00', 'end' => '10:50', 'slot_index' => 0]],
                'Opal'       => [['subject' => 'A', 'session' => 1, 'day' => 'Tuesday', 'start' => '07:30', 'end' => '08:20', 'slot_index' => 7]],
            ],
        ];

        $result = $this->service->checkAntiCollision($plan);
        $this->assertTrue($result['passes']);
    }

    // =========================================================================
    // Invalid grade
    // =========================================================================

    public function test_build_grade_plan_throws_for_grade_11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/G7–G10/');
        $this->service->buildGradePlan(11, []);
    }

    public function test_build_grade_plan_throws_for_grade_6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->buildGradePlan(6, []);
    }

    public function test_build_section_plan_throws_for_grade_12(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->buildSectionPlan(12, 0, []);
    }

    public function test_get_capacity_throws_for_grade_11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getCapacity(11);
    }

    // =========================================================================
    // Empty / edge cases
    // =========================================================================

    public function test_empty_subjects_returns_zero_total_sessions(): void
    {
        $plan = $this->service->buildGradePlan(7, []);
        $this->assertSame(0, $plan['total_sessions']);
    }

    public function test_empty_subjects_returns_empty_placements_per_section(): void
    {
        $plan = $this->service->buildGradePlan(7, []);
        foreach ($plan['sections'] as $sectionName => $placements) {
            $this->assertEmpty($placements, "{$sectionName} should have no placements");
        }
    }

    public function test_single_session_uses_first_rotation_grid_slot(): void
    {
        $plan    = $this->service->buildGradePlan(7, [['name' => 'X', 'sessions_per_week' => 1]]);
        $builder = new RotationGridBuilder();
        $grid    = $builder->buildGrid(7);

        foreach (SC::GRADE_SECTIONS[7] as $idx => $name) {
            $placed  = $plan['sections'][$name][0];
            $gridKey = $grid[$idx][0]['day'] . '|' . $grid[$idx][0]['start'];
            $placed  = $placed['day'] . '|' . $placed['start'];
            $this->assertSame($gridKey, $placed,
                "{$name}: single session must map to grid[{$idx}][0]"
            );
        }
    }

    public function test_all_grades_produce_distinct_slots_within_each_section(): void
    {
        foreach ([7, 8, 9, 10] as $grade) {
            $T        = $this->service->getCapacity($grade);
            $sessions = intdiv($T, 4);   // safe fraction
            $subjects = [['name' => 'Subject', 'sessions_per_week' => $sessions]];
            $plan     = $this->service->buildGradePlan($grade, $subjects);

            foreach ($plan['sections'] as $sectionName => $placements) {
                $keys = array_map(fn ($p) => $p['day'] . '|' . $p['start'], $placements);
                $this->assertSame(count($keys), count(array_unique($keys)),
                    "G{$grade} {$sectionName}: duplicate slots found"
                );
            }
        }
    }
}
