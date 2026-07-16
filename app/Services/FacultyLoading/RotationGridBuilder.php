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
     *   - Friday slots for any grade explicitly configured for ILA (H12)
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
        return $this->getWeeklyClassSlotsForSection($grade, []);
    }

    /**
     * Same as getWeeklyClassSlots(), but rebuilds each day's CLASS slots
     * around a section's own recess/lunch overrides (set in
     * the Sections module) instead of the grade's constant RECESS/LUNCH
     * windows. Any break not overridden by the section falls back to the
     * grade's constant window — RECESS and LUNCH always block (a default
     * exists).
     *
     * @param  int   $grade
     * @param  array $breaks  ['recess'=>['start','end']|null, 'lunch'=>...|null]
     * @return array<int, array{day:string, start:string, end:string, label:string}>
     */
    public function getWeeklyClassSlotsForSection(int $grade, array $breaks): array
    {
        $slots = [];

        foreach (SchedulingConstants::DAYS as $day) {
            foreach ($this->sectionClassSlots($grade, $day, $breaks) as $slot) {
                $check = HardConstraintChecker::checkSpecialDayLocks(
                    $grade, $day, $slot['start'], $slot['end']
                );
                if ($check['passes']) {
                    $slots[] = $slot;
                }
            }
        }

        return $slots;
    }

    /**
     * Rebuild the CLASS-slot list for one grade/day, substituting a section's
     * own recess/lunch windows for the grade's constant ones.
     *
     * Structural (non-configurable) blocks — FLAG, HOMEROOM/ADVISING, DEAD,
     * CONSULT — are taken from the constant timetable unchanged. The day is
     * then greedily walked in fixed-length (PERIOD_MINUTES) chunks, skipping
     * any chunk that overlaps a blocked window, which reproduces the original
     * hand-authored period boundaries exactly when no override is set.
     *
     * @return array<int, array{day:string, start:string, end:string, label:string}>
     */
    private function sectionClassSlots(int $grade, string $day, array $breaks): array
    {
        $timetable = ($day === 'Monday')
            ? SchedulingConstants::getMondayTimetable($grade)
            : SchedulingConstants::getTueFriTimetable($grade);

        $dayStart = min(array_column($timetable, 'start'));
        $dayEnd   = max(array_column($timetable, 'end'));

        $blocked = [];
        foreach ($timetable as $entry) {
            if (! in_array($entry['type'], ['CLASS', 'RECESS', 'LUNCH'], true)) {
                $blocked[] = ['start' => $entry['start'], 'end' => $entry['end']];
            }
        }

        $recess = $breaks['recess'] ?? null;
        if ($recess) {
            $blocked[] = $recess;
        } else {
            foreach (array_filter($timetable, fn ($s) => $s['type'] === 'RECESS') as $s) {
                $blocked[] = ['start' => $s['start'], 'end' => $s['end']];
            }
        }

        $lunch = $breaks['lunch'] ?? null;
        if ($lunch) {
            $blocked[] = $lunch;
        } else {
            foreach (array_filter($timetable, fn ($s) => $s['type'] === 'LUNCH') as $s) {
                $blocked[] = ['start' => $s['start'], 'end' => $s['end']];
            }
        }

        $slots = $this->discretize($day, $dayStart, $dayEnd, $blocked);

        // Grade 8's approved regular overflow periods replace consultation on
        // Tuesday and Thursday. Keep them available in this legacy slot-plan
        // generator as well as in the main deterministic generator.
        if ($grade === 8) {
            foreach (SchedulingConstants::GRADE8_OVERFLOW_SLOTS[$day] ?? [] as $overflow) {
                if ($overflow['type'] !== 'CLASS') {
                    continue;
                }
                $overlapsBreak = collect([$recess, $lunch])
                    ->filter()
                    ->contains(fn ($break) => SchedulingConstants::timesOverlap(
                        $overflow['start'], $overflow['end'], $break['start'], $break['end']
                    ));
                if (! $overlapsBreak) {
                    $slots[] = [
                        'day'   => $day,
                        'start' => $overflow['start'],
                        'end'   => $overflow['end'],
                        'label' => $overflow['label'],
                    ];
                }
            }
        }

        usort($slots, fn ($a, $b) => $this->toMinutes($a['start']) <=> $this->toMinutes($b['start']));

        return $slots;
    }

    /**
     * Greedily walk [dayStart, dayEnd) in PERIOD_MINUTES chunks, skipping any
     * chunk that overlaps a blocked window (jumping the cursor to the end of
     * the furthest-reaching overlapping block before resuming).
     *
     * @return array<int, array{day:string, start:string, end:string, label:string}>
     */
    private function discretize(string $day, string $dayStart, string $dayEnd, array $blocked): array
    {
        $slots   = [];
        $cursor  = $this->toMinutes($dayStart);
        $end     = $this->toMinutes($dayEnd);
        $blockedMin = array_map(fn ($b) => [
            's' => $this->toMinutes($b['start']),
            'e' => $this->toMinutes($b['end']),
        ], $blocked);

        $period = 1;
        while ($cursor + self::PERIOD_MINUTES <= $end) {
            $slotEnd    = $cursor + self::PERIOD_MINUTES;
            $furthestEnd = null;

            foreach ($blockedMin as $b) {
                if ($cursor < $b['e'] && $slotEnd > $b['s']) {
                    $furthestEnd = max($furthestEnd ?? 0, $b['e']);
                }
            }

            if ($furthestEnd !== null) {
                $cursor = $furthestEnd;
                continue;
            }

            $slots[] = [
                'day'   => $day,
                'start' => $this->toTime($cursor),
                'end'   => $this->toTime($slotEnd),
                'label' => "Period {$period}",
            ];
            $period++;
            $cursor = $slotEnd;
        }

        return $slots;
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }

    private function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Standard class period length used across every PSHS-CRC timetable. */
    private const PERIOD_MINUTES = 50;

    /**
     * Build the Latin Square rotation grid for a grade.
     *
     * Each section (0-indexed) gets the full weekly slot list rotated by
     *   offset = sectionIndex * floor(T / numSections)
     *
     * where T = total usable CLASS slots.
     *
     * When $sectionBreaks gives every section in the grade the SAME effective
     * availability (no overrides, or identical overrides), the classic Latin
     * Square rotation is used and the anti-collision guarantee holds exactly
     * as before. When sections have genuinely different break times, each
     * section gets its own independent slot list instead — the Latin Square
     * property can no longer be guaranteed in that case (two sections may
     * legitimately need different times), so rely on checkCrossSection() /
     * checkAntiCollision() to surface any resulting teacher/room clashes.
     *
     * @param  int   $grade  7–10
     * @param  array $sectionBreaks  ['SectionName' => ['recess'=>[..]|null, 'lunch'=>[..]|null], ...]
     * @return array<int, list<array{day:string,start:string,end:string,label:string,slot_index:int,section_index:int,section_name:string}>>
     *               Outer index = section index (0-based).
     *               Inner index = slot position (0 = first preferred slot this week).
     */
    public function buildGrid(int $grade, array $sectionBreaks = []): array
    {
        $sections    = SchedulingConstants::GRADE_SECTIONS[$grade] ?? [];
        $numSections = count($sections);

        if ($numSections === 0) {
            return [];
        }

        $perSectionSlots = [];
        foreach ($sections as $name) {
            $perSectionSlots[$name] = $this->getWeeklyClassSlotsForSection($grade, $sectionBreaks[$name] ?? []);
        }

        $uniform = count(array_unique(array_map(
            fn ($slots) => json_encode($slots),
            $perSectionSlots
        ))) === 1;

        $grid = [];

        if ($uniform) {
            $allSlots = reset($perSectionSlots);
            $T        = count($allSlots);

            if ($T === 0) {
                return [];
            }

            $step = (int) floor($T / $numSections);

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

        // Sections have genuinely different break times — each gets its own
        // independent slot list (no rotation offset; positions don't line up
        // across sections).
        foreach ($sections as $s => $name) {
            $slots = $perSectionSlots[$name];
            $grid[$s] = array_map(fn ($slot, $idx) => array_merge($slot, [
                'slot_index'    => $idx,
                'section_index' => $s,
                'section_name'  => $name,
            ]), $slots, array_keys($slots));
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
