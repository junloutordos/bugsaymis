<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\RotationGridBuilder;
use App\Services\FacultyLoading\SchedulingConstants as SC;
use Tests\TestCase;

/**
 * Tests for RotationGridBuilder — Phase 5.
 *
 * Verifies that the weekly slot lists are correct and that the Latin Square
 * rotation grid satisfies the no-same-slot-at-same-position guarantee.
 */
class RotationGridBuilderTest extends TestCase
{
    private RotationGridBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new RotationGridBuilder();
    }

    // =========================================================================
    // getWeeklyClassSlots — slot counts
    // =========================================================================

    public function test_g7_has_28_weekly_class_slots(): void
    {
        $slots = $this->builder->getWeeklyClassSlots(7);
        $this->assertCount(28, $slots, 'G7 should have 28 usable CLASS slots per week');
    }

    public function test_g8_has_28_weekly_class_slots(): void
    {
        $this->assertCount(28, $this->builder->getWeeklyClassSlots(8));
    }

    public function test_g9_has_36_weekly_class_slots(): void
    {
        $this->assertCount(36, $this->builder->getWeeklyClassSlots(9));
    }

    public function test_g10_has_37_weekly_class_slots(): void
    {
        $this->assertCount(37, $this->builder->getWeeklyClassSlots(10));
    }

    // =========================================================================
    // getWeeklyClassSlots — Friday exclusion (G7/G8)
    // =========================================================================

    public function test_g7_has_no_friday_class_slots(): void
    {
        $slots   = $this->builder->getWeeklyClassSlots(7);
        $fridays = array_filter($slots, fn ($s) => $s['day'] === 'Friday');
        $this->assertEmpty($fridays, 'G7 must have zero Friday slots (ILA day)');
    }

    public function test_g8_has_no_friday_class_slots(): void
    {
        $slots   = $this->builder->getWeeklyClassSlots(8);
        $fridays = array_filter($slots, fn ($s) => $s['day'] === 'Friday');
        $this->assertEmpty($fridays, 'G8 must have zero Friday slots (ILA day)');
    }

    public function test_g9_has_friday_class_slots(): void
    {
        $slots   = $this->builder->getWeeklyClassSlots(9);
        $fridays = array_filter($slots, fn ($s) => $s['day'] === 'Friday');
        $this->assertNotEmpty($fridays, 'G9 must have Friday slots (no ILA)');
    }

    // =========================================================================
    // getWeeklyClassSlots — Wednesday filtering
    // =========================================================================

    public function test_g7_wednesday_has_6_slots(): void
    {
        $slots = $this->builder->getWeeklyClassSlots(7);
        $wed   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Wednesday'));
        $this->assertCount(6, $wed, 'G7 Wednesday should have 6 usable CLASS slots');
    }

    public function test_g9_wednesday_has_6_slots(): void
    {
        $slots = $this->builder->getWeeklyClassSlots(9);
        $wed   = array_values(array_filter($slots, fn ($s) => $s['day'] === 'Wednesday'));
        $this->assertCount(6, $wed, 'G9 Wednesday should have 6 usable CLASS slots');
    }

    public function test_wednesday_slots_exclude_wellness_window(): void
    {
        foreach ([7, 9] as $grade) {
            $slots = $this->builder->getWeeklyClassSlots($grade);
            $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');

            foreach ($wed as $slot) {
                // No slot should overlap 09:50–10:20
                $overlaps = SC::timesOverlap($slot['start'], $slot['end'], '09:50', '10:20');
                $this->assertFalse($overlaps,
                    "G{$grade} Wednesday slot {$slot['start']}–{$slot['end']} overlaps Wellness window"
                );
            }
        }
    }

    public function test_wednesday_slots_exclude_activity_locked_times_g7(): void
    {
        $slots = $this->builder->getWeeklyClassSlots(7);
        $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');

        foreach ($wed as $slot) {
            $this->assertLessThan('15:00', $slot['start'],
                "G7 Wednesday slot {$slot['start']} must start before activity lock 15:00"
            );
        }
    }

    public function test_wednesday_slots_do_not_end_after_activity_lock_g9(): void
    {
        $slots = $this->builder->getWeeklyClassSlots(9);
        $wed   = array_filter($slots, fn ($s) => $s['day'] === 'Wednesday');

        foreach ($wed as $slot) {
            $this->assertLessThanOrEqual('15:00', $slot['end'],
                "G9 Wednesday slot {$slot['start']}–{$slot['end']} must end by 15:00"
            );
        }
    }

    // =========================================================================
    // getWeeklyClassSlots — slot structure
    // =========================================================================

    public function test_weekly_slots_have_required_keys(): void
    {
        $slots = $this->builder->getWeeklyClassSlots(7);
        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $this->assertArrayHasKey('day',   $slot);
            $this->assertArrayHasKey('start', $slot);
            $this->assertArrayHasKey('end',   $slot);
            $this->assertArrayHasKey('label', $slot);
        }
    }

    public function test_weekly_slots_are_ordered_by_canonical_day_then_start(): void
    {
        $slots    = $this->builder->getWeeklyClassSlots(7);
        $dayOrder = array_flip(SC::DAYS);    // Mon=0, Tue=1, ...

        for ($i = 1; $i < count($slots); $i++) {
            $prev = $slots[$i - 1];
            $curr = $slots[$i];

            $prevDay = $dayOrder[$prev['day']];
            $currDay = $dayOrder[$curr['day']];

            $this->assertLessThanOrEqual($currDay, $prevDay,
                "Slot order violation: {$prev['day']} should come before or same as {$curr['day']}"
            );

            if ($prevDay === $currDay) {
                $this->assertLessThanOrEqual($curr['start'], $prev['start'],
                    "Within {$curr['day']}, {$prev['start']} should come before {$curr['start']}"
                );
            }
        }
    }

    // =========================================================================
    // buildGrid — dimensions
    // =========================================================================

    public function test_grid_has_correct_section_count_for_g7(): void
    {
        $grid = $this->builder->buildGrid(7);
        $this->assertCount(4, $grid, 'G7 grid must have 4 sections');
    }

    public function test_grid_has_correct_section_count_for_g9(): void
    {
        $grid = $this->builder->buildGrid(9);
        $this->assertCount(4, $grid);
    }

    public function test_each_section_has_all_t_slots_in_grid(): void
    {
        foreach ([7, 8, 9, 10] as $grade) {
            $T    = count($this->builder->getWeeklyClassSlots($grade));
            $grid = $this->builder->buildGrid($grade);

            foreach ($grid as $s => $slots) {
                $this->assertCount($T, $slots,
                    "G{$grade} section {$s} must have {$T} slot entries in grid"
                );
            }
        }
    }

    public function test_grid_slots_have_slot_index_and_section_metadata(): void
    {
        $grid = $this->builder->buildGrid(7);
        foreach ($grid as $s => $slots) {
            foreach ($slots as $slot) {
                $this->assertArrayHasKey('slot_index',    $slot);
                $this->assertArrayHasKey('section_index', $slot);
                $this->assertArrayHasKey('section_name',  $slot);
                $this->assertSame($s, $slot['section_index']);
            }
        }
    }

    // =========================================================================
    // buildGrid — Latin Square property
    // =========================================================================

    public function test_g7_grid_has_latin_square_property(): void
    {
        $grid = $this->builder->buildGrid(7);
        $this->assertTrue(
            $this->builder->validateGrid($grid),
            'G7 grid must satisfy the Latin Square property'
        );
    }

    public function test_g8_grid_has_latin_square_property(): void
    {
        $grid = $this->builder->buildGrid(8);
        $this->assertTrue($this->builder->validateGrid($grid));
    }

    public function test_g9_grid_has_latin_square_property(): void
    {
        $grid = $this->builder->buildGrid(9);
        $this->assertTrue($this->builder->validateGrid($grid));
    }

    public function test_g10_grid_has_latin_square_property_in_safe_range(): void
    {
        // G10 has T=37, step=9. Validate first 9*4=36 positions only.
        $grid        = $this->builder->buildGrid(10);
        $numSections = count($grid);
        $step        = $this->builder->getRotationStep(10);
        $safeLen     = $step * $numSections;

        for ($i = 0; $i < $safeLen; $i++) {
            $seen = [];
            for ($s = 0; $s < $numSections; $s++) {
                $key = $grid[$s][$i]['day'] . '|' . $grid[$s][$i]['start'];
                $this->assertArrayNotHasKey($key, $seen,
                    "G10 position {$i}: section {$s} collides with a previous section at {$key}"
                );
                $seen[$key] = $s;
            }
        }
    }

    /**
     * The core anti-collision guarantee: if a teacher is assigned position i
     * across all 4 sections, they have no (day, time) conflict.
     */
    public function test_no_two_sections_share_slot_at_same_position_for_g7(): void
    {
        $grid    = $this->builder->buildGrid(7);
        $T       = count($grid[0]);
        $step    = $this->builder->getRotationStep(7);
        $safeLen = $step * count($grid);

        for ($i = 0; $i < $safeLen; $i++) {
            $keys = array_map(
                fn ($s) => $grid[$s][$i]['day'] . '|' . $grid[$s][$i]['start'],
                array_keys($grid)
            );
            $this->assertSame(
                count($keys),
                count(array_unique($keys)),
                "G7 position {$i}: two or more sections share the same (day, start)"
            );
        }
    }

    // =========================================================================
    // getRotationStep
    // =========================================================================

    public function test_rotation_step_is_7_for_g7(): void
    {
        $this->assertSame(7, $this->builder->getRotationStep(7));
    }

    public function test_rotation_step_is_7_for_g8(): void
    {
        $this->assertSame(7, $this->builder->getRotationStep(8));
    }

    public function test_rotation_step_is_9_for_g9(): void
    {
        $this->assertSame(9, $this->builder->getRotationStep(9));
    }

    public function test_rotation_step_is_9_for_g10(): void
    {
        $this->assertSame(9, $this->builder->getRotationStep(10));
    }

    // =========================================================================
    // getSuggestedSlot
    // =========================================================================

    public function test_get_suggested_slot_returns_slot_for_valid_indices(): void
    {
        $slot = $this->builder->getSuggestedSlot(7, 0, 0);
        $this->assertIsArray($slot);
        $this->assertArrayHasKey('day',   $slot);
        $this->assertArrayHasKey('start', $slot);
    }

    public function test_get_suggested_slot_returns_null_for_out_of_range(): void
    {
        $this->assertNull($this->builder->getSuggestedSlot(7, 0, 9999));
    }

    public function test_sections_0_and_1_get_different_first_slot(): void
    {
        $s0 = $this->builder->getSuggestedSlot(7, 0, 0);
        $s1 = $this->builder->getSuggestedSlot(7, 1, 0);

        $this->assertNotNull($s0);
        $this->assertNotNull($s1);
        $key0 = $s0['day'] . '|' . $s0['start'];
        $key1 = $s1['day'] . '|' . $s1['start'];
        $this->assertNotSame($key0, $key1,
            'Sections 0 and 1 must have different first suggested slots'
        );
    }

    public function test_all_four_sections_have_distinct_first_slots_for_g7(): void
    {
        $keys = [];
        for ($s = 0; $s < 4; $s++) {
            $slot   = $this->builder->getSuggestedSlot(7, $s, 0);
            $keys[] = $slot['day'] . '|' . $slot['start'];
        }
        $this->assertCount(4, array_unique($keys),
            'All 4 G7 sections must have distinct first suggested slots'
        );
    }

    // =========================================================================
    // validateGrid — rejects invalid grids
    // =========================================================================

    public function test_validate_grid_rejects_empty_array(): void
    {
        $this->assertFalse($this->builder->validateGrid([]));
    }

    public function test_validate_grid_rejects_grid_with_collision(): void
    {
        // Craft a grid where section 0 and section 1 share the same slot at position 0
        $fakeSlot = ['day' => 'Monday', 'start' => '10:00', 'end' => '10:50', 'label' => 'P1'];
        $bad = [
            0 => [$fakeSlot],
            1 => [$fakeSlot],   // same slot — should fail
        ];
        $this->assertFalse($this->builder->validateGrid($bad));
    }

    public function test_validate_grid_accepts_grid_with_no_collision(): void
    {
        $slot0 = ['day' => 'Monday',  'start' => '10:00', 'end' => '10:50', 'label' => 'P1'];
        $slot1 = ['day' => 'Tuesday', 'start' => '07:30', 'end' => '08:20', 'label' => 'P1'];
        $good  = [
            0 => [$slot0],
            1 => [$slot1],
        ];
        $this->assertTrue($this->builder->validateGrid($good));
    }
}
