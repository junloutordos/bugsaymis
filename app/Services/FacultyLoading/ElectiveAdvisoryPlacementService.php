<?php

namespace App\Services\FacultyLoading;

/**
 * ElectiveAdvisoryPlacementService
 *
 * Phase 8 — Plans slot assignments for elective subjects and identifies
 * the advisory / SCALE blocks for all grades.
 *
 * Elective windows:
 *   G7/G8/G9 — no dedicated elective windows (empty candidate list)
 *   G10       — 2 slots  (Wed 14:10–15:00, Thu 14:10–15:00)
 *   G11/G12   — 5 slots  (Tue×2, Wed×1, Thu×2)
 *
 * Candidate slots are found by overlapping the ELECTIVE_WINDOWS_* constants
 * with the grade's CLASS-slot timetable, then filtering through H9–H12
 * constant constraints.
 *
 * Advisory blocks (Monday 08:00–08:50 — all grades):
 *   G7–G10  → HOMEROOM
 *   G11–G12 → ADVISING (Academic Advising)
 *
 * SCALE Advising (G11/G12 only — Tuesday 11:10–12:00):
 *   Fixed block; not a teaching class; exposed for scheduling awareness.
 *
 * Elective placement model:
 *   Electives are PARALLEL — all sections of a grade run their respective
 *   elective classes at the same (day, start, end).  Therefore the plan
 *   produced by buildElectivePlan assigns identical slots to every section,
 *   while the subject and teacher assignments differ per section.
 *
 * This service is pure-logic (no DB reads/writes).
 *
 * Placement plan shape (output of buildElectivePlan):
 * [
 *   'grade'          => 10,
 *   'total_sessions' => 2,
 *   'unfilled'       => [],   // sessions that exceed elective capacity
 *   'sections'       => [
 *     'Electron' => [
 *       ['subject'=>'STE Elective', 'session'=>1, 'day'=>'Wednesday', 'start'=>'14:10', 'end'=>'15:00'],
 *       ['subject'=>'STE Elective', 'session'=>2, 'day'=>'Thursday',  'start'=>'14:10', 'end'=>'15:00'],
 *     ],
 *     'Graviton' => [...],   // identical slots, different subjects possible
 *     ...
 *   ],
 * ]
 */
class ElectiveAdvisoryPlacementService
{
    // =========================================================================
    // Elective slot discovery
    // =========================================================================

    /**
     * Return all valid CLASS slots that fall within the elective windows for
     * a grade.  Ordered by day (canonical week order) then start time.
     *
     * Uses overlap semantics: a CLASS slot is included if it overlaps with
     * any defined elective window, and passes H9–H12 constant constraints.
     *
     * G7/G8/G9 → []
     * G10       → 2 slots
     * G11/G12   → 5 slots
     *
     * @param  int   $grade
     * @return array<int, array{day:string, start:string, end:string, label:string}>
     */
    public function getCandidateElectiveSlots(int $grade): array
    {
        $windows = $this->getElectiveWindows($grade);
        $seen    = [];
        $slots   = [];

        foreach ($windows as $window) {
            foreach ($this->getSlotsInWindow($grade, $window['day'], $window['start'], $window['end']) as $slot) {
                $key = $slot['day'] . '|' . $slot['start'];
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $slots[]    = $slot;
                }
            }
        }

        return $slots;
    }

    /**
     * Return the number of available elective slots for a grade.
     * This is the maximum sessions_per_week sum before unfilled entries appear.
     */
    public function getElectiveCapacity(int $grade): int
    {
        return count($this->getCandidateElectiveSlots($grade));
    }

    // =========================================================================
    // Elective placement
    // =========================================================================

    /**
     * Build a parallel elective placement plan for all sections of a grade.
     *
     * All sections receive the SAME (day, start, end) for each session position
     * because elective periods are parallel — every section is simultaneously
     * in its own elective class.
     *
     * @param  int   $grade            10, 11, or 12  (G7/G8/G9 always return empty sections)
     * @param  array $electiveSubjects [['name' => string, 'sessions_per_week' => int], ...]
     * @return array Placement plan (see class docblock for shape)
     */
    public function buildElectivePlan(int $grade, array $electiveSubjects): array
    {
        $candidateSlots = $this->getCandidateElectiveSlots($grade);
        $sections       = SchedulingConstants::GRADE_SECTIONS[$grade] ?? [];
        $sessionList    = $this->expandToSessions($electiveSubjects);
        $totalSessions  = count($sessionList);

        // Build the common placement list (same for every section — parallel)
        $commonPlacements = [];
        $unfilled         = [];

        foreach ($sessionList as $idx => $sessionItem) {
            if (! isset($candidateSlots[$idx])) {
                $unfilled[] = [
                    'subject' => $sessionItem['name'],
                    'session' => $sessionItem['session'],
                ];
                continue;
            }

            $slot               = $candidateSlots[$idx];
            $commonPlacements[] = [
                'subject' => $sessionItem['name'],
                'session' => $sessionItem['session'],
                'day'     => $slot['day'],
                'start'   => $slot['start'],
                'end'     => $slot['end'],
            ];
        }

        $plan = [];
        foreach ($sections as $sectionName) {
            $plan[$sectionName] = $commonPlacements;
        }

        return [
            'grade'          => $grade,
            'total_sessions' => $totalSessions,
            'unfilled'       => $unfilled,
            'sections'       => $plan,
        ];
    }

    /**
     * Build an elective plan for a single section (non-parallel variant).
     *
     * Useful when each section has a different elective subject list, or when
     * re-planning one section with pre-existing bookings.
     *
     * @param  int   $grade        Any grade (empty result for G7/G8/G9)
     * @param  array $subjects     [['name' => string, 'sessions_per_week' => int], ...]
     * @param  array $claimedKeys  Pre-claimed 'day|start' keys to skip
     * @return array<int, array{subject:string, session:int, day:string, start:string, end:string}>
     */
    public function buildSectionElectivePlan(
        int   $grade,
        array $subjects,
        array $claimedKeys = []
    ): array {
        $candidateSlots = $this->getCandidateElectiveSlots($grade);
        $sessionList    = $this->expandToSessions($subjects);
        $claimed        = $claimedKeys;
        $placements     = [];

        foreach ($sessionList as $sessionItem) {
            $placed = false;
            foreach ($candidateSlots as $slot) {
                $key = $slot['day'] . '|' . $slot['start'];
                if (! in_array($key, $claimed, true)) {
                    $claimed[]    = $key;
                    $placements[] = [
                        'subject' => $sessionItem['name'],
                        'session' => $sessionItem['session'],
                        'day'     => $slot['day'],
                        'start'   => $slot['start'],
                        'end'     => $slot['end'],
                    ];
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                break;  // no more elective slots available
            }
        }

        return $placements;
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Validate an elective plan produced by buildElectivePlan.
     *
     * Checks:
     *   1. No duplicate (day, start) within any section
     *   2. Every slot is within an elective window for the grade
     *   3. Every slot passes H9–H12 constant constraints
     *
     * @param  array $plan  Output of buildElectivePlan
     * @return array{passes: bool, violations: list<string>}
     */
    public function validateElectivePlan(array $plan): array
    {
        $violations = [];
        $grade      = $plan['grade'] ?? 0;

        foreach ($plan['sections'] ?? [] as $sectionName => $placements) {
            $seen = [];
            foreach ($placements as $p) {
                $key = $p['day'] . '|' . $p['start'];

                if (isset($seen[$key])) {
                    $violations[] = "{$sectionName}: duplicate slot {$key} "
                        . "for {$p['subject']} session {$p['session']}";
                }
                $seen[$key] = true;

                if (! $this->isInElectiveWindow($grade, $p['day'], $p['start'], $p['end'])) {
                    $violations[] = "{$sectionName}: {$p['subject']} s{$p['session']} — "
                        . "{$p['day']} {$p['start']} is not within an elective window";
                }

                $check = HardConstraintChecker::checkSpecialDayLocks(
                    $grade, $p['day'], $p['start'], $p['end']
                );
                if (! $check['passes']) {
                    $violations[] = "{$sectionName} {$p['subject']} s{$p['session']}: "
                        . $check['reason'];
                }
            }
        }

        return [
            'passes'     => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * True if (day, start, end) overlaps with any elective window for the grade.
     *
     * @param  int    $grade
     * @param  string $day
     * @param  string $start  HH:MM
     * @param  string $end    HH:MM
     * @return bool
     */
    public function isInElectiveWindow(int $grade, string $day, string $start, string $end): bool
    {
        foreach ($this->getElectiveWindows($grade) as $window) {
            if ($window['day'] === $day
                && SchedulingConstants::timesOverlap($start, $end, $window['start'], $window['end'])
            ) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // Advisory / fixed blocks
    // =========================================================================

    /**
     * Return the homeroom/advising slot descriptor for a grade.
     *
     * All grades: Monday 08:00–08:50.
     * G7–G10 → type = 'HOMEROOM'
     * G11–G12 → type = 'ADVISING'
     *
     * @param  int   $grade
     * @return array{day:string, start:string, end:string, type:string, label:string}
     */
    public function getAdvisorySlot(int $grade): array
    {
        $isUpper = $grade >= 11;

        return [
            'day'   => 'Monday',
            'start' => SchedulingConstants::HOMEROOM_BLOCK['start'],
            'end'   => SchedulingConstants::HOMEROOM_BLOCK['end'],
            'type'  => $isUpper ? 'ADVISING'  : 'HOMEROOM',
            'label' => $isUpper ? 'Academic Advising' : 'Homeroom Class',
        ];
    }

    /**
     * Return the SCALE Advising slot descriptor (G11/G12 only).
     *
     * Tuesday 11:10–12:00 — not a teaching class; reserved for SCALE advising.
     *
     * @return array{day:string, start:string, end:string}
     */
    public function getScaleAdvisingSlot(): array
    {
        return SchedulingConstants::SCALE_ADVISING;
    }

    /**
     * True if the given (day, start, end) matches an advisory or SCALE block
     * for the grade.
     *
     * Advisory (all grades): Monday 08:00–08:50.
     * SCALE Advising (G11/G12): Tuesday 11:10–12:00.
     *
     * @param  int    $grade
     * @param  string $day
     * @param  string $start  HH:MM
     * @param  string $end    HH:MM
     * @return bool
     */
    public function isAdvisoryBlock(int $grade, string $day, string $start, string $end): bool
    {
        // Homeroom / Advising: Monday 08:00–08:50 (all grades)
        if ($day === 'Monday'
            && $start === SchedulingConstants::HOMEROOM_BLOCK['start']
            && $end   === SchedulingConstants::HOMEROOM_BLOCK['end']
        ) {
            return true;
        }

        // SCALE Advising: G11/G12 only, Tuesday 11:10–12:00
        $scale = SchedulingConstants::SCALE_ADVISING;
        if ($grade >= 11
            && $day   === $scale['day']
            && $start === $scale['start']
            && $end   === $scale['end']
        ) {
            return true;
        }

        return false;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Return the elective window definitions for a grade.
     */
    private function getElectiveWindows(int $grade): array
    {
        return match (true) {
            $grade === 10    => SchedulingConstants::ELECTIVE_WINDOWS_G10,
            $grade >= 11     => SchedulingConstants::ELECTIVE_WINDOWS_G11G12,
            default          => [],
        };
    }

    /**
     * Return all CLASS slots for a grade/day that overlap with [winStart, winEnd)
     * AND pass H9–H12 constant constraints.
     *
     * @return array<int, array{day:string, start:string, end:string, label:string}>
     */
    private function getSlotsInWindow(
        int    $grade,
        string $day,
        string $winStart,
        string $winEnd
    ): array {
        $slots = [];

        foreach (SchedulingConstants::getClassSlots($grade, $day) as $slot) {
            if (! SchedulingConstants::timesOverlap($slot['start'], $slot['end'], $winStart, $winEnd)) {
                continue;
            }

            $check = HardConstraintChecker::checkSpecialDayLocks($grade, $day, $slot['start'], $slot['end']);
            if ($check['passes']) {
                $slots[] = array_merge($slot, ['day' => $day]);
            }
        }

        return $slots;
    }

    /**
     * Expand a subject list into a flat session list.
     *
     * [['name'=>'Elective','sessions_per_week'=>2]] →
     * [['name'=>'Elective','session'=>1], ['name'=>'Elective','session'=>2]]
     */
    private function expandToSessions(array $subjects): array
    {
        $sessions = [];
        foreach ($subjects as $subject) {
            $n    = max(1, (int) ($subject['sessions_per_week'] ?? 1));
            $name = (string) ($subject['name'] ?? '');
            for ($i = 1; $i <= $n; $i++) {
                $sessions[] = ['name' => $name, 'session' => $i];
            }
        }
        return $sessions;
    }
}
