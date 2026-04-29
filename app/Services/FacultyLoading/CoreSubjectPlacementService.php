<?php

namespace App\Services\FacultyLoading;

/**
 * CoreSubjectPlacementService
 *
 * Phase 7 — Plans slot assignments for core subjects across all sections
 * of a grade (G7–G10) using the RotationGridBuilder Latin Square grid.
 *
 * Key concepts:
 *   - Each "subject item" has a name and sessions_per_week count.
 *   - Subject items are consumed in order; each session takes the next
 *     available slot from that section's rotated slot list.
 *   - The Latin Square property guarantees that at session position i,
 *     no two sections occupy the same (day, start) — so any teacher
 *     assigned position i across sections has zero scheduling conflicts.
 *   - Falls back to the next unclaimed slot when a grid position is
 *     already taken (e.g., pre-existing external bookings).
 *
 * This service is pure-logic (no DB reads/writes). The returned plan is
 * consumed by the schedule writer to create class_schedule rows.
 *
 * Placement plan shape (output of buildGradePlan):
 * [
 *   'grade'          => 7,
 *   'total_sessions' => 14,    // sum of all sessions_per_week values
 *   'unfilled'       => [],    // session items that could not be placed
 *   'sections'       => [
 *     'Aquamarine' => [
 *       [
 *         'subject'    => 'English',
 *         'session'    => 1,          // 1-based session counter for that subject
 *         'day'        => 'Monday',
 *         'start'      => '10:00',
 *         'end'        => '10:50',
 *         'slot_index' => 4,          // index in the full weekly CLASS slot list
 *       ],
 *       ...
 *     ],
 *     'Opal'       => [...],
 *     'Turquoise'  => [...],
 *     'Sapphire'   => [...],
 *   ],
 * ]
 *
 * Subject item shape (input):
 *   ['name' => 'English', 'sessions_per_week' => 5]
 */
class CoreSubjectPlacementService
{
    public function __construct(
        private readonly RotationGridBuilder $rotationGrid
    ) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Build the full core subject placement plan for all sections of a grade.
     *
     * Subjects are expanded into individual sessions and assigned to slots in
     * rotation-grid order.  The same session index is consumed across all
     * sections, preserving the Latin Square anti-collision guarantee.
     *
     * @param  int   $grade     7–10
     * @param  array $subjects  [['name' => string, 'sessions_per_week' => int], ...]
     * @return array            Placement plan (see class docblock for shape)
     * @throws \InvalidArgumentException  if grade is outside G7–G10
     */
    public function buildGradePlan(int $grade, array $subjects): array
    {
        $this->assertRotationGrade($grade);

        $sections    = SchedulingConstants::GRADE_SECTIONS[$grade];
        $numSections = count($sections);
        $rotGrid     = $this->rotationGrid->buildGrid($grade);

        $sessionList   = $this->expandToSessions($subjects);
        $totalSessions = count($sessionList);

        $plan     = [];
        $unfilled = [];

        for ($s = 0; $s < $numSections; $s++) {
            $sectionName  = $sections[$s];
            $sectionSlots = $rotGrid[$s] ?? [];
            $claimed      = [];
            $placements   = [];

            foreach ($sessionList as $sessionItem) {
                $slot = $this->findNextUnclaimed($sectionSlots, $claimed);

                if ($slot === null) {
                    $unfilled[] = [
                        'section' => $sectionName,
                        'subject' => $sessionItem['name'],
                        'session' => $sessionItem['session'],
                    ];
                    continue;
                }

                $claimed[]    = $slot['day'] . '|' . $slot['start'];
                $placements[] = [
                    'subject'    => $sessionItem['name'],
                    'session'    => $sessionItem['session'],
                    'day'        => $slot['day'],
                    'start'      => $slot['start'],
                    'end'        => $slot['end'],
                    'slot_index' => $slot['slot_index'],
                ];
            }

            $plan[$sectionName] = $placements;
        }

        return [
            'grade'          => $grade,
            'total_sessions' => $totalSessions,
            'unfilled'       => $unfilled,
            'sections'       => $plan,
        ];
    }

    /**
     * Build a placement plan for a single section.
     *
     * Useful when integrating per-section external bookings or when only
     * one section needs to be (re-)planned.
     *
     * @param  int   $grade          7–10
     * @param  int   $sectionIndex   0-based index into GRADE_SECTIONS[$grade]
     * @param  array $subjects       [['name' => string, 'sessions_per_week' => int], ...]
     * @param  array $claimedKeys    Pre-claimed 'day|start' keys to skip
     * @return array<int, array{subject:string,session:int,day:string,start:string,end:string,slot_index:int}>
     */
    public function buildSectionPlan(
        int   $grade,
        int   $sectionIndex,
        array $subjects,
        array $claimedKeys = []
    ): array {
        $this->assertRotationGrade($grade);

        $rotGrid      = $this->rotationGrid->buildGrid($grade);
        $sectionSlots = $rotGrid[$sectionIndex] ?? [];

        if (empty($sectionSlots)) {
            return [];
        }

        $sessionList = $this->expandToSessions($subjects);
        $claimed     = $claimedKeys;
        $placements  = [];

        foreach ($sessionList as $sessionItem) {
            $slot = $this->findNextUnclaimed($sectionSlots, $claimed);
            if ($slot === null) {
                break;
            }
            $claimed[]    = $slot['day'] . '|' . $slot['start'];
            $placements[] = [
                'subject'    => $sessionItem['name'],
                'session'    => $sessionItem['session'],
                'day'        => $slot['day'],
                'start'      => $slot['start'],
                'end'        => $slot['end'],
                'slot_index' => $slot['slot_index'],
            ];
        }

        return $placements;
    }

    /**
     * Validate a placement plan produced by buildGradePlan.
     *
     * Checks:
     *   1. No two placements within a section share the same (day, start)
     *   2. Every slot passes HardConstraintChecker::checkSpecialDayLocks
     *
     * @param  array $plan  Output of buildGradePlan
     * @return array{passes: bool, violations: list<string>}
     */
    public function validatePlan(array $plan): array
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

                $check = HardConstraintChecker::checkSpecialDayLocks(
                    $grade, $p['day'], $p['start'], $p['end']
                );
                if (! $check['passes']) {
                    $violations[] = "{$sectionName} {$p['subject']} "
                        . "s{$p['session']}: {$check['reason']}";
                }
            }
        }

        return [
            'passes'     => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Check the anti-collision property across all sections in a grade plan.
     *
     * At each session position i, no two sections should share the same
     * (day, start) pair — that would create a teacher conflict if the same
     * teacher is assigned the same subject across sections.
     *
     * @param  array $plan  Output of buildGradePlan
     * @return array{passes: bool, collisions: list<string>}
     */
    public function checkAntiCollision(array $plan): array
    {
        $sections   = array_values($plan['sections'] ?? []);
        $collisions = [];

        if (count($sections) < 2) {
            return ['passes' => true, 'collisions' => []];
        }

        $maxLen = max(array_map('count', $sections));

        for ($i = 0; $i < $maxLen; $i++) {
            $seen = [];
            foreach ($sections as $idx => $placements) {
                if (! isset($placements[$i])) {
                    continue;
                }
                $key = $placements[$i]['day'] . '|' . $placements[$i]['start'];
                if (isset($seen[$key])) {
                    $collisions[] = "session position {$i}: section {$idx} "
                        . "collides with section {$seen[$key]} at {$key}";
                } else {
                    $seen[$key] = $idx;
                }
            }
        }

        return [
            'passes'     => empty($collisions),
            'collisions' => $collisions,
        ];
    }

    /**
     * Return how many sessions can be placed for a grade given the available
     * CLASS slots per section (= total usable slots T).
     *
     * This is the maximum sessions_per_week sum before unfilled entries appear.
     */
    public function getCapacity(int $grade): int
    {
        $this->assertRotationGrade($grade);
        return count($this->rotationGrid->getWeeklyClassSlots($grade));
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Expand a subject list into a flat session list.
     *
     * [['name'=>'English','sessions_per_week'=>3]] →
     * [
     *   ['name'=>'English','session'=>1],
     *   ['name'=>'English','session'=>2],
     *   ['name'=>'English','session'=>3],
     * ]
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

    /**
     * Return the first slot from $sectionSlots whose 'day|start' key is not
     * present in $claimed.  Scans in rotation order (front to back).
     *
     * @param  array $sectionSlots  Ordered slot list for one section
     * @param  array $claimed       Already-used 'day|start' keys
     * @return array|null
     */
    private function findNextUnclaimed(array $sectionSlots, array $claimed): ?array
    {
        foreach ($sectionSlots as $slot) {
            $key = $slot['day'] . '|' . $slot['start'];
            if (! in_array($key, $claimed, true)) {
                return $slot;
            }
        }
        return null;
    }

    /**
     * Assert that a grade is within the G7–G10 range handled by this service.
     */
    private function assertRotationGrade(int $grade): void
    {
        if ($grade < 7 || $grade > 10) {
            throw new \InvalidArgumentException(
                "CoreSubjectPlacementService handles G7–G10 only (got G{$grade})"
            );
        }
    }
}
