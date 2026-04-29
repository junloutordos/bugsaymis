<?php

namespace App\Services\FacultyLoading;

/**
 * RotationGridBuilder
 *
 * Produces a Latin Square rotation grid for G7–G10.
 *
 * Problem: a single teacher often covers the same subject across all 4 sections
 * of a grade (e.g., one English teacher for G7-Aquamarine, Opal, Turquoise,
 * and Sapphire). Their sessions across sections must not land on the same
 * (day, time) pair — otherwise H1 (teacher no-overlap) fires for every slot.
 *
 * Solution: for each grade, enumerate all usable CLASS slots across the week,
 * then rotate that list by T/numSections per section. This guarantees that at
 * any slot position i, no two sections have the same (day, start) time — so
 * a teacher who is assigned position i in multiple sections has zero conflicts.
 *
 * Usage in the generator
 * ──────────────────────
 *   $grid = (new RotationGridBuilder)->buildGrid($grade);
 *
 *   // First preferred slot for section 2, second session of subject:
 *   $slot = $grid[2][1];   // ['day'=>'Tue', 'start'=>'10:20', ...]
 *
 * Latin Square guarantee
 * ──────────────────────
 *   For positions 0..(step*numSections - 1), every column i has distinct
 *   (day, start) values across all sections.  For grades where T is not
 *   evenly divisible (G10: T=37, step=9), the final T % numSections
 *   positions may have collisions and should not be used for primary
 *   placement; the generator should fall back to the first-available
 *   unclaimed slot instead.
 */
class RotationGridBuilder
{
    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Get all usable CLASS slots for a grade across a typical week.
     *
     * Filters out:
     *   - Friday slots for G7/G8 (ILA day — H12)
     *   - Wednesday slots blocked by Wellness or Activity lock (H11, H10)
     *
     * Returns a flat array ordered by canonical day order then ascending
     * start time, matching the slot order in SchedulingConstants.
     *
     * @param  int   $grade  7–10
     * @return array<int, array{day:string, start:string, end:string, label:string}>
     */
    public function getWeeklyClassSlots(int $grade): array
    {
        $slots = [];

        foreach (SchedulingConstants::DAYS as $day) {
            foreach (SchedulingConstants::getClassSlots($grade, $day) as $slot) {
                $check = HardConstraintChecker::checkSpecialDayLocks(
                    $grade, $day, $slot['start'], $slot['end']
                );
                if ($check['passes']) {
                    $slots[] = [
                        'day'   => $day,
                        'start' => $slot['start'],
                        'end'   => $slot['end'],
                        'label' => $slot['label'] ?? '',
                    ];
                }
            }
        }

        return $slots;
    }

    /**
     * Build the Latin Square rotation grid for a grade.
     *
     * Each section (0-indexed) gets the full weekly slot list rotated by
     *   offset = sectionIndex * floor(T / numSections)
     *
     * where T = total usable CLASS slots.
     *
     * @param  int   $grade  7–10
     * @return array<int, list<array{day:string,start:string,end:string,label:string,slot_index:int,section_index:int,section_name:string}>>
     *               Outer index = section index (0-based).
     *               Inner index = slot position (0 = first preferred slot this week).
     */
    public function buildGrid(int $grade): array
    {
        $allSlots    = $this->getWeeklyClassSlots($grade);
        $T           = count($allSlots);
        $sections    = SchedulingConstants::GRADE_SECTIONS[$grade] ?? [];
        $numSections = count($sections);

        if ($T === 0 || $numSections === 0) {
            return [];
        }

        $step = (int) floor($T / $numSections);
        $grid = [];

        for ($s = 0; $s < $numSections; $s++) {
            $offset       = ($s * $step) % $T;
            $sectionSlots = [];

            for ($i = 0; $i < $T; $i++) {
                $idx            = ($offset + $i) % $T;
                $sectionSlots[] = array_merge($allSlots[$idx], [
                    'slot_index'    => $idx,
                    'section_index' => $s,
                    'section_name'  => $sections[$s],
                ]);
            }

            $grid[$s] = $sectionSlots;
        }

        return $grid;
    }

    /**
     * Return the rotation step size for a grade (floor(T / numSections)).
     * This equals the minimum number of conflict-free primary placements
     * the generator can make per teacher across all sections.
     */
    public function getRotationStep(int $grade): int
    {
        $T           = count($this->getWeeklyClassSlots($grade));
        $numSections = count(SchedulingConstants::GRADE_SECTIONS[$grade] ?? []);

        if ($numSections === 0) {
            return 0;
        }

        return (int) floor($T / $numSections);
    }

    /**
     * Validate the Latin Square property for the "safe" portion of the grid:
     * for positions 0..(step*numSections - 1), every column has distinct
     * (day, start) values across all sections.
     *
     * @param  array $grid   Output of buildGrid()
     * @return bool
     */
    public function validateGrid(array $grid): bool
    {
        if (empty($grid)) {
            return false;
        }

        $numSections = count($grid);
        $numSlots    = min(array_map('count', $grid));
        // For grids produced by buildGrid(), validate only the "perfect" portion
        // (first step*numSections positions). When the grid is too small to compute
        // a positive step, validate every position.
        $step    = (int) floor($numSlots / $numSections);
        $safeLen = $step > 0 ? $step * $numSections : $numSlots;

        for ($i = 0; $i < $safeLen; $i++) {
            $seen = [];
            for ($s = 0; $s < $numSections; $s++) {
                if (! isset($grid[$s][$i])) {
                    return false;
                }
                $key = $grid[$s][$i]['day'] . '|' . $grid[$s][$i]['start'];
                if (isset($seen[$key])) {
                    return false;
                }
                $seen[$key] = true;
            }
        }

        return true;
    }

    /**
     * Get the suggested (day, slot) for a specific section and subject-session
     * position.  The generator uses this as its first-choice placement and
     * falls back if H1/H2/H14 DB constraints reject it.
     *
     * @param  int      $grade         7–10
     * @param  int      $sectionIndex  0-based index into GRADE_SECTIONS[$grade]
     * @param  int      $subjectSlot   0-based session index (0 = first session)
     * @return array|null              Slot array or null if out of range
     */
    public function getSuggestedSlot(int $grade, int $sectionIndex, int $subjectSlot): ?array
    {
        $grid = $this->buildGrid($grade);
        return $grid[$sectionIndex][$subjectSlot] ?? null;
    }

    // =========================================================================
    // Diagnostic / debug helpers
    // =========================================================================

    /**
     * Return a human-readable summary of the grid for a grade.
     * Useful for logging during generation runs.
     *
     * @param  int  $grade
     * @return array<string, array<int, string>>
     *               ['SectionName' => ['Mon 10:00', 'Tue 07:30', ...]]
     */
    public function summarizeGrid(int $grade): array
    {
        $grid     = $this->buildGrid($grade);
        $sections = SchedulingConstants::GRADE_SECTIONS[$grade] ?? [];
        $summary  = [];

        foreach ($grid as $s => $slots) {
            $name          = $sections[$s] ?? "Section{$s}";
            $summary[$name] = array_map(
                static fn ($sl) => substr($sl['day'], 0, 3) . ' ' . $sl['start'],
                $slots
            );
        }

        return $summary;
    }
}
