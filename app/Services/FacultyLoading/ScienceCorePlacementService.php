<?php

namespace App\Services\FacultyLoading;

/**
 * ScienceCorePlacementService
 *
 * Handles Hard Constraint H15: all sections of a grade must hold their
 * Science Core subject at the SAME (day, start, end) timeslot, with each
 * section assigned a DISTINCT science subject.
 *
 *   G11: Venus → Physics 3 | Mars → Biology 3 | Mercury → Chemistry 3
 *   G12: Del Mundo → Physics 4 | Orosa → Biology 4 | Zara → Chemistry 4
 *
 * Science Core may only be placed on SCIENCE_CORE_DAYS:
 *   Monday, Wednesday, Thursday, Friday  (Tuesday excluded)
 *
 * This service is pure-constant (no DB writes). The placement plan it
 * returns is consumed by Phase 7 (Core Subject Placement) to create the
 * actual class_schedule rows via the scheduler.
 *
 * Placement plan shape (output of buildPlacementPlan):
 * [
 *   'grade'      => 11,
 *   'day'        => 'Thursday',
 *   'start'      => '09:10',
 *   'end'        => '10:00',
 *   'placements' => [
 *     ['section' => 'Venus',   'subject' => 'Physics 3'],
 *     ['section' => 'Mars',    'subject' => 'Biology 3'],
 *     ['section' => 'Mercury', 'subject' => 'Chemistry 3'],
 *   ],
 * ]
 */
class ScienceCorePlacementService
{
    // =========================================================================
    // Candidate slot discovery
    // =========================================================================

    /**
     * Return all CLASS slots eligible for Science Core placement for a grade.
     *
     * Filters: Science Core days only + H9-H12 constant constraints.
     * Ordered by canonical day (Mon→Wed→Thu→Fri) then ascending start time.
     *
     * @param  int   $grade  11 or 12
     * @return array<int, array{day:string, start:string, end:string, label:string}>
     */
    public function getCandidateSlots(int $grade): array
    {
        $slots = [];

        foreach (SchedulingConstants::SCIENCE_CORE_DAYS as $day) {
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
     * Return the first eligible Science Core slot not already in $bookedSlots.
     *
     * @param  int   $grade        11 or 12
     * @param  array $bookedSlots  Array of ['day'=>..., 'start'=>...] to skip
     * @return array|null          First available slot, or null if fully booked
     */
    public function findParallelSlot(int $grade, array $bookedSlots = []): ?array
    {
        $bookedKeys = array_map(
            static fn ($s) => $s['day'] . '|' . $s['start'],
            $bookedSlots
        );

        foreach ($this->getCandidateSlots($grade) as $slot) {
            if (! in_array($slot['day'] . '|' . $slot['start'], $bookedKeys, true)) {
                return $slot;
            }
        }

        return null;
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Validate that a proposed (day, start, end) can serve as the shared
     * Science Core slot for a grade.
     *
     * Checks (in order):
     *   1. Day must be a SCIENCE_CORE_DAY (not Tuesday)
     *   2. Must pass H9–H12 constant constraints
     *   3. Must be an actual CLASS slot in the G11/G12 timetable
     *
     * @return array{passes: bool, reason: string|null}
     */
    public function validateParallelPlacement(
        int    $grade,
        string $day,
        string $start,
        string $end
    ): array {
        // 1. Science Core day
        if (! in_array($day, SchedulingConstants::SCIENCE_CORE_DAYS, true)) {
            $allowed = implode(', ', SchedulingConstants::SCIENCE_CORE_DAYS);
            return [
                'passes' => false,
                'reason' => "H15: Science Core must be on a Science Core day ({$allowed}) — '{$day}' is not allowed",
            ];
        }

        // 2. Special day locks
        $check = HardConstraintChecker::checkSpecialDayLocks($grade, $day, $start, $end);
        if (! $check['passes']) {
            return $check;
        }

        // 3. Must be a valid CLASS slot
        $classSlots = SchedulingConstants::getClassSlots($grade, $day);
        $isValid    = false;
        foreach ($classSlots as $slot) {
            if ($slot['start'] === $start && $slot['end'] === $end) {
                $isValid = true;
                break;
            }
        }

        if (! $isValid) {
            return [
                'passes' => false,
                'reason' => "H15: {$start}–{$end} on {$day} is not a valid CLASS slot in the G{$grade} timetable",
            ];
        }

        return ['passes' => true, 'reason' => null];
    }

    /**
     * H15 compliance check on a list of already-proposed placements.
     *
     * Expects each element to contain: day, start, end, section, subject.
     * Passes iff:
     *   - All placements share the same (day, start, end)
     *   - All subjects are distinct
     *   - All sections are distinct
     *
     * @param  array $proposedPlacements
     * @return array{passes: bool, reason: string|null}
     */
    public function validateH15(array $proposedPlacements): array
    {
        if (empty($proposedPlacements)) {
            return ['passes' => false, 'reason' => 'H15: No placements provided'];
        }

        // All must share the same timeslot
        $timeKeys = array_unique(array_map(
            static fn ($p) => $p['day'] . '|' . $p['start'] . '|' . $p['end'],
            $proposedPlacements
        ));

        if (count($timeKeys) !== 1) {
            return [
                'passes' => false,
                'reason' => 'H15: All Science Core sections must share the same (day, start, end) — '
                    . count($timeKeys) . ' different timeslots found',
            ];
        }

        // All subjects must be distinct
        $subjects = array_column($proposedPlacements, 'subject');
        if (count($subjects) !== count(array_unique($subjects))) {
            return [
                'passes' => false,
                'reason' => 'H15: Each Science Core section must have a distinct subject — '
                    . 'duplicate subject found in proposed placements',
            ];
        }

        // All sections must be distinct
        $sections = array_column($proposedPlacements, 'section');
        if (count($sections) !== count(array_unique($sections))) {
            return [
                'passes' => false,
                'reason' => 'H15: Duplicate section in Science Core placements',
            ];
        }

        return ['passes' => true, 'reason' => null];
    }

    // =========================================================================
    // Subject lookup
    // =========================================================================

    /**
     * Return the Science Core subject name for a section of a grade.
     *
     * @param  int    $grade        11 or 12
     * @param  string $sectionName  e.g., 'Venus', 'Mars', 'Del Mundo'
     * @return string               e.g., 'Physics 3', 'Biology 4'
     * @throws \InvalidArgumentException  if section is not found
     */
    public function getScienceCoreSubject(int $grade, string $sectionName): string
    {
        $map = match ($grade) {
            11      => SchedulingConstants::SCIENCE_CORE_G11,
            12      => SchedulingConstants::SCIENCE_CORE_G12,
            default => throw new \InvalidArgumentException(
                "Science Core is only defined for G11 and G12 (got G{$grade})"
            ),
        };

        if (! isset($map[$sectionName])) {
            throw new \InvalidArgumentException(
                "Section '{$sectionName}' not found in G{$grade} Science Core map"
            );
        }

        return $map[$sectionName];
    }

    // =========================================================================
    // Placement plan builder
    // =========================================================================

    /**
     * Build the full parallel placement plan for a grade's Science Core at a
     * given (day, start, end).
     *
     * Validates the slot first; throws on invalid input.
     *
     * @param  int    $grade  11 or 12
     * @param  string $day
     * @param  string $start  HH:MM
     * @param  string $end    HH:MM
     * @return array  Placement plan (see class docblock for shape)
     * @throws \InvalidArgumentException  on invalid slot
     */
    public function buildPlacementPlan(
        int    $grade,
        string $day,
        string $start,
        string $end
    ): array {
        $validation = $this->validateParallelPlacement($grade, $day, $start, $end);
        if (! $validation['passes']) {
            throw new \InvalidArgumentException($validation['reason']);
        }

        $sections   = SchedulingConstants::GRADE_SECTIONS[$grade];
        $subjectMap = $grade === 11
            ? SchedulingConstants::SCIENCE_CORE_G11
            : SchedulingConstants::SCIENCE_CORE_G12;

        $placements = [];
        foreach ($sections as $sectionName) {
            $placements[] = [
                'section' => $sectionName,
                'subject' => $subjectMap[$sectionName],
            ];
        }

        return [
            'grade'      => $grade,
            'day'        => $day,
            'start'      => $start,
            'end'        => $end,
            'placements' => $placements,
        ];
    }

    /**
     * Auto-select the first available slot and build the placement plan.
     * Convenience wrapper: find + build in one call.
     *
     * @param  int   $grade        11 or 12
     * @param  array $bookedSlots  Slots already claimed by other subjects
     * @return array|null          Placement plan, or null if no slot is available
     */
    public function autoPlace(int $grade, array $bookedSlots = []): ?array
    {
        $slot = $this->findParallelSlot($grade, $bookedSlots);
        if ($slot === null) {
            return null;
        }

        return $this->buildPlacementPlan($grade, $slot['day'], $slot['start'], $slot['end']);
    }
}
