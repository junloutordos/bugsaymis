<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\ScheduleGeneratorService;
use App\Services\FacultyLoading\CoreSubjectPlacementService;
use App\Services\FacultyLoading\ElectiveAdvisoryPlacementService;
use App\Services\FacultyLoading\ScienceCorePlacementService;
use App\Services\FacultyLoading\SchedulingConstants;
use Tests\TestCase;

/**
 * ScheduleGeneratorServiceTest
 *
 * Covers Phase 11 orchestration:
 *   – generateSlotPlan returns correct shape for all grades
 *   – Core subjects placed for G7–G10 (rotation grid) and G11–G12 (sequential)
 *   – Science subjects placed for G7–G10, absent in G11–G12
 *   – Electives placed for G10–G12, absent for G7–G9
 *   – Advisory block always present; SCALE block for G11/G12 only
 *   – All sections present in result; no duplicate slots within a section
 *   – Unresolved items tracked when capacity exceeded
 *   – Metrics accurate
 *   – validateResult detects constraint violations and duplicate slots
 *   – checkCrossSection validates anti-collision property (G7–G10)
 */
class ScheduleGeneratorServiceTest extends TestCase
{
    private ScheduleGeneratorService $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ScheduleGeneratorService(
            app(CoreSubjectPlacementService::class),
            new ElectiveAdvisoryPlacementService(),
            new ScienceCorePlacementService(),
        );
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    /** Minimal G7/G8 core subjects totalling exactly T slots (28 for G7) */
    private function g7CoreSubjects(): array
    {
        return [
            ['name' => 'Math',    'type' => 'core', 'sessions_per_week' => 5],
            ['name' => 'English', 'type' => 'core', 'sessions_per_week' => 5],
            ['name' => 'Filipino','type' => 'core', 'sessions_per_week' => 4],
            ['name' => 'Science', 'type' => 'core', 'sessions_per_week' => 5],
            ['name' => 'Araling', 'type' => 'core', 'sessions_per_week' => 4],
            ['name' => 'MAPEH',   'type' => 'core', 'sessions_per_week' => 3],
            ['name' => 'TLE',     'type' => 'core', 'sessions_per_week' => 2],
        ];  // total = 28
    }

    /** Small set that won't fill capacity — to test partial placement */
    private function smallCoreSubjects(): array
    {
        return [
            ['name' => 'Math', 'type' => 'core', 'sessions_per_week' => 2],
        ];
    }

    /** Science Core — signals to include G11/G12 science_core block */
    private function scienceSubjects(): array
    {
        return [
            ['name' => 'Science Core', 'type' => 'science_core', 'sessions_per_week' => 1],
        ];
    }

    private function electiveSubjects(): array
    {
        return [
            ['name' => 'PE Elective', 'type' => 'elective', 'sessions_per_week' => 2],
        ];
    }

    // =========================================================================
    // Result shape
    // =========================================================================

    /** @test */
    public function test_result_has_required_keys_for_g7(): void
    {
        $result = $this->generator->generateSlotPlan(
            7,
            $this->g7CoreSubjects(),
            SchedulingConstants::GRADE_SECTIONS[7]
        );

        $this->assertArrayHasKey('grade',      $result);
        $this->assertArrayHasKey('advisory',   $result);
        $this->assertArrayHasKey('scale',      $result);
        $this->assertArrayHasKey('sections',   $result);
        $this->assertArrayHasKey('unresolved', $result);
        $this->assertArrayHasKey('metrics',    $result);
    }

    /** @test */
    public function test_grade_is_preserved_in_result(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            $sections = SchedulingConstants::GRADE_SECTIONS[$grade];
            $result   = $this->generator->generateSlotPlan($grade, [], $sections);
            $this->assertSame($grade, $result['grade']);
        }
    }

    /** @test */
    public function test_all_sections_present_in_result_for_g7(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[7];
        $result = $this->generator->generateSlotPlan(7, $this->g7CoreSubjects(), $sectionNames);

        foreach ($sectionNames as $name) {
            $this->assertArrayHasKey($name, $result['sections'],
                "Section '{$name}' missing from result");
        }
    }

    /** @test */
    public function test_all_sections_present_for_all_grades(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            $sectionNames = SchedulingConstants::GRADE_SECTIONS[$grade];
            $result = $this->generator->generateSlotPlan($grade, [], $sectionNames);
            foreach ($sectionNames as $name) {
                $this->assertArrayHasKey($name, $result['sections'],
                    "Grade {$grade} section '{$name}' missing");
            }
        }
    }

    // =========================================================================
    // Advisory & SCALE blocks
    // =========================================================================

    /** @test */
    public function test_advisory_block_present_for_all_grades(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $grade) {
            $result = $this->generator->generateSlotPlan(
                $grade, [], SchedulingConstants::GRADE_SECTIONS[$grade]
            );
            $advisory = $result['advisory'];
            $this->assertSame('Monday', $advisory['day']);
            $this->assertSame('08:00',  $advisory['start']);
            $this->assertSame('08:50',  $advisory['end']);
        }
    }

    /** @test */
    public function test_advisory_type_is_homeroom_for_g7_to_g10(): void
    {
        foreach ([7, 8, 9, 10] as $grade) {
            $result = $this->generator->generateSlotPlan(
                $grade, [], SchedulingConstants::GRADE_SECTIONS[$grade]
            );
            $this->assertSame('HOMEROOM', $result['advisory']['type']);
        }
    }

    /** @test */
    public function test_advisory_type_is_advising_for_g11_g12(): void
    {
        foreach ([11, 12] as $grade) {
            $result = $this->generator->generateSlotPlan(
                $grade, [], SchedulingConstants::GRADE_SECTIONS[$grade]
            );
            $this->assertSame('ADVISING', $result['advisory']['type']);
        }
    }

    /** @test */
    public function test_scale_block_null_for_g7_to_g10(): void
    {
        foreach ([7, 8, 9, 10] as $grade) {
            $result = $this->generator->generateSlotPlan(
                $grade, [], SchedulingConstants::GRADE_SECTIONS[$grade]
            );
            $this->assertNull($result['scale'],
                "Grade {$grade} should have null SCALE block");
        }
    }

    /** @test */
    public function test_scale_block_present_for_g11_g12(): void
    {
        foreach ([11, 12] as $grade) {
            $result = $this->generator->generateSlotPlan(
                $grade, [], SchedulingConstants::GRADE_SECTIONS[$grade]
            );
            $this->assertNotNull($result['scale']);
            $this->assertArrayHasKey('day',   $result['scale']);
            $this->assertArrayHasKey('start', $result['scale']);
            $this->assertArrayHasKey('end',   $result['scale']);
            $this->assertSame('Tuesday', $result['scale']['day']);
            $this->assertSame('11:10',   $result['scale']['start']);
        }
    }

    // =========================================================================
    // Core subject placement — G7–G10
    // =========================================================================

    /** @test */
    public function test_core_subjects_placed_for_g7(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[7];
        $result = $this->generator->generateSlotPlan(7, $this->g7CoreSubjects(), $sectionNames);

        foreach ($sectionNames as $name) {
            $this->assertNotEmpty($result['sections'][$name],
                "G7 section '{$name}' has no placements");
        }
    }

    /** @test */
    public function test_core_placement_count_matches_sessions_per_week_g8(): void
    {
        $subjects     = $this->smallCoreSubjects(); // Math × 2
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[8];
        $result       = $this->generator->generateSlotPlan(8, $subjects, $sectionNames);

        // Each G8 section should have exactly 2 placements for Math
        foreach ($sectionNames as $name) {
            $mathPlacements = array_filter(
                $result['sections'][$name],
                fn ($p) => $p['subject'] === 'Math'
            );
            $this->assertCount(2, array_values($mathPlacements),
                "G8 section '{$name}' should have 2 Math sessions");
        }
    }

    /** @test */
    public function test_no_duplicate_slots_within_a_section_g7(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[7];
        $result = $this->generator->generateSlotPlan(7, $this->g7CoreSubjects(), $sectionNames);

        foreach ($sectionNames as $name) {
            $seen = [];
            foreach ($result['sections'][$name] as $p) {
                $key = $p['day_of_week'] . '|' . $p['start_time'];
                $this->assertNotContains($key, $seen,
                    "G7 section '{$name}': duplicate slot {$key}");
                $seen[] = $key;
            }
        }
    }

    /** @test */
    public function test_no_duplicate_slots_within_a_section_for_all_junior_grades(): void
    {
        foreach ([7, 8, 9, 10] as $grade) {
            $sectionNames = SchedulingConstants::GRADE_SECTIONS[$grade];
            $result = $this->generator->generateSlotPlan($grade, $this->g7CoreSubjects(), $sectionNames);

            foreach ($sectionNames as $name) {
                $keys = array_map(
                    fn ($p) => $p['day_of_week'] . '|' . $p['start_time'],
                    $result['sections'][$name]
                );
                $this->assertSame(
                    count($keys),
                    count(array_unique($keys)),
                    "Grade {$grade} section '{$name}' has duplicate slots"
                );
            }
        }
    }

    /** @test */
    public function test_core_placement_type_is_core(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->smallCoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        foreach (SchedulingConstants::GRADE_SECTIONS[8] as $name) {
            foreach ($result['sections'][$name] as $p) {
                $this->assertSame('core', $p['type']);
            }
        }
    }

    /** @test */
    public function test_placement_has_required_keys(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->smallCoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        $firstSection = array_key_first($result['sections']);
        $placement    = $result['sections'][$firstSection][0];

        $this->assertArrayHasKey('subject',     $placement);
        $this->assertArrayHasKey('type',        $placement);
        $this->assertArrayHasKey('session',     $placement);
        $this->assertArrayHasKey('day_of_week', $placement);
        $this->assertArrayHasKey('start_time',  $placement);
        $this->assertArrayHasKey('end_time',    $placement);
    }

    // =========================================================================
    // Science Core placement — G11/G12 only
    // =========================================================================

    /** @test */
    public function test_science_core_placed_for_g11(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[11];
        $result = $this->generator->generateSlotPlan(11, $this->scienceSubjects(), $sectionNames);

        foreach ($sectionNames as $name) {
            $sciencePlacements = array_filter(
                $result['sections'][$name],
                fn ($p) => $p['type'] === 'science_core'
            );
            $this->assertNotEmpty(array_values($sciencePlacements),
                "G11 section '{$name}' has no science_core placements");
        }
    }

    /** @test */
    public function test_science_core_placed_for_g12(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[12];
        $result = $this->generator->generateSlotPlan(12, $this->scienceSubjects(), $sectionNames);

        foreach ($sectionNames as $name) {
            $sciencePlacements = array_filter(
                $result['sections'][$name],
                fn ($p) => $p['type'] === 'science_core'
            );
            $this->assertNotEmpty(array_values($sciencePlacements),
                "G12 section '{$name}' has no science_core placements");
        }
    }

    /** @test */
    public function test_science_core_not_placed_for_g7_to_g10(): void
    {
        foreach ([7, 8, 9, 10] as $grade) {
            $sectionNames = SchedulingConstants::GRADE_SECTIONS[$grade];
            $result = $this->generator->generateSlotPlan($grade, $this->scienceSubjects(), $sectionNames);

            foreach ($sectionNames as $name) {
                $sciencePlacements = array_filter(
                    $result['sections'][$name],
                    fn ($p) => $p['type'] === 'science_core'
                );
                $this->assertEmpty(array_values($sciencePlacements),
                    "Grade {$grade} section '{$name}' should have no science_core placements");
            }
        }
    }

    /** @test */
    public function test_science_core_same_slot_for_all_g11_sections(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[11];
        $result = $this->generator->generateSlotPlan(11, $this->scienceSubjects(), $sectionNames);

        $slots = [];
        foreach ($sectionNames as $name) {
            $science = array_values(array_filter(
                $result['sections'][$name], fn ($p) => $p['type'] === 'science_core'
            ));
            if (! empty($science)) {
                $slots[] = $science[0]['day_of_week'] . '|' . $science[0]['start_time'];
            }
        }

        // All sections should share the same timeslot (parallel placement)
        $this->assertCount(1, array_unique($slots),
            'G11 sections have different science_core slots (should be parallel)');
    }

    /** @test */
    public function test_science_core_has_different_subjects_per_g11_section(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[11];
        $result = $this->generator->generateSlotPlan(11, $this->scienceSubjects(), $sectionNames);

        $subjects = [];
        foreach ($sectionNames as $name) {
            $science = array_values(array_filter(
                $result['sections'][$name], fn ($p) => $p['type'] === 'science_core'
            ));
            if (! empty($science)) {
                $subjects[] = $science[0]['subject'];
            }
        }

        // Each section gets a different Science Core subject
        $this->assertSame(count($sectionNames), count(array_unique($subjects)),
            'G11 sections should have distinct Science Core subjects');
    }

    // =========================================================================
    // Elective placement — G10–G12
    // =========================================================================

    /** @test */
    public function test_electives_placed_for_g10(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[10];
        $result = $this->generator->generateSlotPlan(10, $this->electiveSubjects(), $sectionNames);

        foreach ($sectionNames as $name) {
            $electivePlacements = array_filter(
                $result['sections'][$name], fn ($p) => $p['type'] === 'elective'
            );
            $this->assertNotEmpty(array_values($electivePlacements),
                "G10 section '{$name}' has no elective placements");
        }
    }

    /** @test */
    public function test_electives_placed_for_g11(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[11];
        $result = $this->generator->generateSlotPlan(11, $this->electiveSubjects(), $sectionNames);

        foreach ($sectionNames as $name) {
            $electivePlacements = array_filter(
                $result['sections'][$name], fn ($p) => $p['type'] === 'elective'
            );
            $this->assertNotEmpty(array_values($electivePlacements));
        }
    }

    /** @test */
    public function test_no_electives_for_g7_g8_g9(): void
    {
        foreach ([7, 8, 9] as $grade) {
            $sectionNames = SchedulingConstants::GRADE_SECTIONS[$grade];
            $result = $this->generator->generateSlotPlan($grade, $this->electiveSubjects(), $sectionNames);

            foreach ($sectionNames as $name) {
                $electivePlacements = array_filter(
                    $result['sections'][$name], fn ($p) => $p['type'] === 'elective'
                );
                $this->assertEmpty(array_values($electivePlacements),
                    "Grade {$grade} section '{$name}' should have no electives");
            }
        }
    }

    // =========================================================================
    // Mixed subject types
    // =========================================================================

    /** @test */
    public function test_core_and_elective_placed_for_g10(): void
    {
        $subjects = array_merge(
            $this->smallCoreSubjects(),
            [['name' => 'PE Elective', 'type' => 'elective', 'sessions_per_week' => 1]],
        );

        $sectionNames = SchedulingConstants::GRADE_SECTIONS[10];
        $result       = $this->generator->generateSlotPlan(10, $subjects, $sectionNames);

        foreach ($sectionNames as $name) {
            $types = array_column($result['sections'][$name], 'type');
            $this->assertContains('core',     $types, "G10 '{$name}' missing core");
            $this->assertContains('elective', $types, "G10 '{$name}' missing elective");
        }
    }

    /** @test */
    public function test_core_science_and_elective_placed_for_g11(): void
    {
        $subjects = array_merge(
            $this->smallCoreSubjects(),
            $this->scienceSubjects(),
            $this->electiveSubjects(),
        );

        $sectionNames = SchedulingConstants::GRADE_SECTIONS[11];
        $result       = $this->generator->generateSlotPlan(11, $subjects, $sectionNames);

        foreach ($sectionNames as $name) {
            $types = array_column($result['sections'][$name], 'type');
            $this->assertContains('core',         $types, "G11 '{$name}' missing core");
            $this->assertContains('science_core', $types, "G11 '{$name}' missing science_core");
            $this->assertContains('elective',     $types, "G11 '{$name}' missing elective");
        }
    }

    /** @test */
    public function test_no_duplicate_slots_with_core_and_elective_g10(): void
    {
        $subjects = array_merge(
            $this->smallCoreSubjects(),
            $this->electiveSubjects(),
        );

        $sectionNames = SchedulingConstants::GRADE_SECTIONS[10];
        $result       = $this->generator->generateSlotPlan(10, $subjects, $sectionNames);

        foreach ($sectionNames as $name) {
            $keys = array_map(
                fn ($p) => $p['day_of_week'] . '|' . $p['start_time'],
                $result['sections'][$name]
            );
            $this->assertSame(
                count($keys),
                count(array_unique($keys)),
                "G10 section '{$name}' has duplicate slots with mixed types"
            );
        }
    }

    // =========================================================================
    // G11/G12 core placement
    // =========================================================================

    /** @test */
    public function test_core_subjects_placed_for_g11(): void
    {
        $subjects     = $this->smallCoreSubjects();
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[11];
        $result       = $this->generator->generateSlotPlan(11, $subjects, $sectionNames);

        foreach ($sectionNames as $name) {
            $corePlacements = array_filter(
                $result['sections'][$name], fn ($p) => $p['type'] === 'core'
            );
            $this->assertNotEmpty(array_values($corePlacements),
                "G11 section '{$name}' has no core placements");
        }
    }

    /** @test */
    public function test_no_duplicate_slots_for_g12(): void
    {
        $subjects = [
            ['name' => 'Core A', 'type' => 'core', 'sessions_per_week' => 3],
            ['name' => 'Core B', 'type' => 'core', 'sessions_per_week' => 3],
        ];
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[12];
        $result       = $this->generator->generateSlotPlan(12, $subjects, $sectionNames);

        foreach ($sectionNames as $name) {
            $keys = array_map(
                fn ($p) => $p['day_of_week'] . '|' . $p['start_time'],
                $result['sections'][$name]
            );
            $this->assertSame(
                count($keys),
                count(array_unique($keys)),
                "G12 section '{$name}' has duplicate slots"
            );
        }
    }

    // =========================================================================
    // Unresolved tracking
    // =========================================================================

    /** @test */
    public function test_unresolved_empty_when_capacity_not_exceeded(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->smallCoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        $this->assertEmpty($result['unresolved']);
    }

    /** @test */
    public function test_unresolved_appears_when_capacity_exceeded(): void
    {
        // G7 capacity = 28 slots; 30 sessions exceeds it
        $subjects = [
            ['name' => 'Math',    'type' => 'core', 'sessions_per_week' => 10],
            ['name' => 'English', 'type' => 'core', 'sessions_per_week' => 10],
            ['name' => 'Science', 'type' => 'core', 'sessions_per_week' => 10],
        ];  // 30 total > 28 capacity

        $result = $this->generator->generateSlotPlan(7, $subjects, SchedulingConstants::GRADE_SECTIONS[7]);

        $this->assertNotEmpty($result['unresolved']);
        foreach ($result['unresolved'] as $u) {
            $this->assertArrayHasKey('subject', $u);
            $this->assertArrayHasKey('type',    $u);
            $this->assertArrayHasKey('session', $u);
        }
    }

    // =========================================================================
    // Metrics
    // =========================================================================

    /** @test */
    public function test_metrics_have_required_keys(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->smallCoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        $this->assertArrayHasKey('total_sessions',   $result['metrics']);
        $this->assertArrayHasKey('placed_count',     $result['metrics']);
        $this->assertArrayHasKey('unresolved_count', $result['metrics']);
    }

    /** @test */
    public function test_metrics_placed_count_matches_sections(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->smallCoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        $actualPlaced = array_sum(array_map('count', $result['sections']));
        $this->assertSame($actualPlaced, $result['metrics']['placed_count']);
    }

    /** @test */
    public function test_metrics_total_equals_placed_plus_unresolved(): void
    {
        $subjects = [
            ['name' => 'Math',    'type' => 'core', 'sessions_per_week' => 10],
            ['name' => 'English', 'type' => 'core', 'sessions_per_week' => 10],
            ['name' => 'Science', 'type' => 'core', 'sessions_per_week' => 10],
        ];

        $result = $this->generator->generateSlotPlan(7, $subjects, SchedulingConstants::GRADE_SECTIONS[7]);

        $this->assertSame(
            $result['metrics']['placed_count'] + $result['metrics']['unresolved_count'],
            $result['metrics']['total_sessions']
        );
    }

    // =========================================================================
    // validateResult
    // =========================================================================

    /** @test */
    public function test_validate_result_passes_for_valid_g8_plan(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->g7CoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        $validation = $this->generator->validateResult($result);

        $this->assertTrue($validation['passes'],
            'Validation failed: ' . implode('; ', $validation['violations']));
        $this->assertEmpty($validation['violations']);
    }

    /** @test */
    public function test_validate_result_fails_for_duplicate_slot(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->smallCoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        // Manually inject a duplicate slot into the first section
        $sectionName = array_key_first($result['sections']);
        $first       = $result['sections'][$sectionName][0];
        $result['sections'][$sectionName][] = [
            'subject'     => 'Injected',
            'type'        => 'core',
            'session'     => 1,
            'day_of_week' => $first['day_of_week'],
            'start_time'  => $first['start_time'],
            'end_time'    => $first['end_time'],
        ];

        $validation = $this->generator->validateResult($result);

        $this->assertFalse($validation['passes']);
        $this->assertNotEmpty($validation['violations']);
        $this->assertStringContainsString('duplicate slot', $validation['violations'][0]);
    }

    /** @test */
    public function test_validate_result_passes_for_g10_mixed(): void
    {
        $subjects = array_merge(
            $this->smallCoreSubjects(),
            $this->scienceSubjects(),
            $this->electiveSubjects(),
        );

        $result     = $this->generator->generateSlotPlan(10, $subjects, SchedulingConstants::GRADE_SECTIONS[10]);
        $validation = $this->generator->validateResult($result);

        $this->assertTrue($validation['passes'],
            'Mixed G10 plan failed validation: ' . implode('; ', $validation['violations']));
    }

    // =========================================================================
    // checkCrossSection (no two sections teach same subject at same slot)
    // =========================================================================

    /** @test */
    public function test_check_cross_section_passes_for_valid_g7_plan(): void
    {
        $result = $this->generator->generateSlotPlan(
            7, $this->g7CoreSubjects(), SchedulingConstants::GRADE_SECTIONS[7]
        );

        $check = $this->generator->checkCrossSection($result);

        // Rotation grid guarantees no two sections have the same subject at the same slot
        $this->assertTrue($check['passes'],
            'Cross-section collision detected: ' . implode('; ', $check['violations']));
    }

    /** @test */
    public function test_check_cross_section_passes_for_g8(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->g7CoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        $check = $this->generator->checkCrossSection($result);

        $this->assertTrue($check['passes'],
            'Cross-section collision: ' . implode('; ', $check['violations']));
    }

    /** @test */
    public function test_check_cross_section_detects_same_subject_at_same_slot(): void
    {
        $result = $this->generator->generateSlotPlan(
            8, $this->smallCoreSubjects(), SchedulingConstants::GRADE_SECTIONS[8]
        );

        // Inject the SAME (subject + slot) into two different sections
        $sections  = array_keys($result['sections']);
        $dummySlot = [
            'subject'     => 'CollidingSubject',
            'type'        => 'core',
            'session'     => 1,
            'day_of_week' => 'Thursday',
            'start_time'  => '13:10',
            'end_time'    => '14:00',
        ];
        $result['sections'][$sections[0]][] = $dummySlot;
        $result['sections'][$sections[1]][] = $dummySlot;

        $check = $this->generator->checkCrossSection($result);

        $this->assertFalse($check['passes']);
        $this->assertNotEmpty($check['violations']);
        $this->assertStringContainsString('CollidingSubject', $check['violations'][0]);
    }

    /** @test */
    public function test_check_cross_section_ignores_parallel_elective_slots(): void
    {
        // Electives are intentionally parallel — all sections share the same slot
        $subjects     = $this->electiveSubjects();
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[10];
        $result       = $this->generator->generateSlotPlan(10, $subjects, $sectionNames);

        // checkCrossSection should not flag the elective slots as collisions
        $check = $this->generator->checkCrossSection($result);

        $this->assertTrue($check['passes'],
            'Parallel electives wrongly flagged: ' . implode('; ', $check['violations']));
    }

    // =========================================================================
    // Empty subject list
    // =========================================================================

    /** @test */
    public function test_empty_subjects_returns_empty_sections(): void
    {
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[8];
        $result       = $this->generator->generateSlotPlan(8, [], $sectionNames);

        $this->assertEmpty($result['unresolved']);
        foreach ($sectionNames as $name) {
            $this->assertEmpty($result['sections'][$name]);
        }
        $this->assertSame(0, $result['metrics']['placed_count']);
        $this->assertSame(0, $result['metrics']['total_sessions']);
    }
}
