<?php

namespace App\Services\FacultyLoading;

/**
 * ScheduleGeneratorService
 *
 * Phase 11 — Top-level orchestrator for AI-assisted schedule generation.
 *
 * Coordinates the four placement services into a single call that produces
 * a complete slot plan for all sections of a grade.
 *
 * Placement pipeline (in execution order):
 *   1. Core subjects   — CoreSubjectPlacementService  (G7–G10 rotation grid)
 *                        or sequential scan            (G11–G12)
 *   2. Science Core    — ScienceCorePlacementService  (G7–G10, parallel)
 *   3. Electives       — ElectiveAdvisoryPlacementService (G10–G12, parallel)
 *   4. Advisory block  — fixed; included in result but not placed as a class
 *
 * This service is PURE LOGIC — no DB reads/writes.
 *
 * ── Subject type values ───────────────────────────────────────────────────────
 *   'core'     — regular teaching class, placed using the rotation grid for
 *                G7–G10 or sequential scan for G11–G12
 *   'science'  — Science Core parallel block (G7–G10 only)
 *   'elective' — parallel elective block (G10–G12)
 *
 * ── Input $subjects format ────────────────────────────────────────────────────
 *   [
 *     ['name' => 'Mathematics', 'type' => 'core',    'sessions_per_week' => 5],
 *     ['name' => 'Science',     'type' => 'science', 'sessions_per_week' => 3],
 *     ['name' => 'PE Elective', 'type' => 'elective','sessions_per_week' => 2],
 *   ]
 *
 * ── GenerationResult shape ────────────────────────────────────────────────────
 *   [
 *     'grade'      => int,
 *     'advisory'   => ['day'=>..., 'start'=>..., 'end'=>..., 'type'=>..., 'label'=>...],
 *     'scale'      => ['day'=>..., 'start'=>..., 'end'=>...]|null,  // G11/G12 only
 *     'sections'   => [
 *       'SectionName' => [
 *         [
 *           'subject'    => string,
 *           'type'       => 'core'|'science'|'elective',
 *           'session'    => int,
 *           'day_of_week'=> string,
 *           'start_time' => string,
 *           'end_time'   => string,
 *         ],
 *         ...
 *       ],
 *       ...
 *     ],
 *     'unresolved' => [
 *       ['subject'=>string, 'type'=>string, 'session'=>int],
 *       ...
 *     ],
 *     'metrics'    => [
 *       'total_sessions'   => int,
 *       'placed_count'     => int,
 *       'unresolved_count' => int,
 *     ],
 *   ]
 */
class ScheduleGeneratorService
{
    public function __construct(
        private readonly CoreSubjectPlacementService       $corePlacer,
        private readonly ElectiveAdvisoryPlacementService  $electivePlacer,
        private readonly ScienceCorePlacementService       $sciencePlacer,
    ) {}

    // =========================================================================
    // Primary entry point
    // =========================================================================

    /**
     * Generate a complete slot plan for all sections of a grade.
     *
     * Core subjects for G7–G10 use the rotation grid (anti-collision guarantee).
     * Core subjects for G11–G12 use sequential slot scanning per section.
     * Science Core (G7–G10) and Electives (G10–G12) use parallel placement.
     * Advisory blocks are always included as fixed metadata.
     *
     * @param  int   $grade        7–12
     * @param  array $subjects     Subject list with 'type', 'name', 'sessions_per_week' keys
     * @param  array $sectionNames Ordered section names (must match GRADE_SECTIONS order)
     * @return array               GenerationResult (see class docblock)
     */
    public function generateSlotPlan(int $grade, array $subjects, array $sectionNames): array
    {
        $coreSubjects        = $this->filterByType($subjects, 'core');
        $scienceCoreSubjects = $this->filterByType($subjects, 'science_core');
        $electiveSubjects    = $this->filterByType($subjects, 'elective');

        $sections   = [];
        $unresolved = [];

        // ── 1. Elective placement (G10–G12) — FIRST so core can avoid these windows
        $reservedElectiveKeys = [];
        if (! empty($electiveSubjects) && $grade >= 10) {
            $electivePlan = $this->electivePlacer->buildElectivePlan($grade, $electiveSubjects);
            $this->mergePlanIntoSections($electivePlan['sections'], 'elective', $sections);
            foreach ($electivePlan['unfilled'] as $u) {
                $unresolved[] = array_merge($u, ['type' => 'elective']);
            }
            // Collect reserved elective slot keys so core placement avoids them
            $reservedElectiveKeys = array_unique(array_map(
                fn ($s) => $s['day'] . '|' . $s['start'],
                $this->electivePlacer->getCandidateElectiveSlots($grade)
            ));
        }

        // ── 2. Science Core placement (G11–G12 only via autoPlace) ────────
        // ScienceCorePlacementService maps each section to a distinct lab subject
        // automatically (SCIENCE_CORE_G11 / SCIENCE_CORE_G12 constants).
        // A non-empty $scienceCoreSubjects list signals that the block is needed.
        if (! empty($scienceCoreSubjects) && $grade >= 11) {
            $sciencePlan = $this->sciencePlacer->autoPlace($grade);
            if ($sciencePlan !== null) {
                foreach ($sciencePlan['placements'] as $p) {
                    if (! isset($sections[$p['section']])) {
                        $sections[$p['section']] = [];
                    }
                    $sections[$p['section']][] = [
                        'subject'     => $p['subject'],
                        'type'        => 'science_core',
                        'session'     => 1,
                        'day_of_week' => $sciencePlan['day'],
                        'start_time'  => $sciencePlan['start'],
                        'end_time'    => $sciencePlan['end'],
                    ];
                }
            }
        }

        // ── 3. Core subject placement (AFTER electives — avoid reserved windows)
        if (! empty($coreSubjects)) {
            if ($grade >= 7 && $grade <= 10) {
                $this->placeJuniorCore($grade, $coreSubjects, $reservedElectiveKeys, $sections, $unresolved);
            } else {
                $this->placeSeniorCore($grade, $coreSubjects, $sectionNames, $sections, $unresolved);
            }
        }

        // ── 4. Ensure every requested section exists in the result ─────────
        foreach ($sectionNames as $name) {
            if (! isset($sections[$name])) {
                $sections[$name] = [];
            }
        }

        // ── 5. Advisory / SCALE blocks (metadata only) ────────────────────
        $advisory = $this->electivePlacer->getAdvisorySlot($grade);
        $scale    = $grade >= 11 ? $this->electivePlacer->getScaleAdvisingSlot() : null;

        // ── 6. Metrics ─────────────────────────────────────────────────────
        $placedCount   = array_sum(array_map('count', $sections));
        $totalSessions = $placedCount + count($unresolved);

        return [
            'grade'      => $grade,
            'advisory'   => $advisory,
            'scale'      => $scale,
            'sections'   => $sections,
            'unresolved' => $unresolved,
            'metrics'    => [
                'total_sessions'   => $totalSessions,
                'placed_count'     => $placedCount,
                'unresolved_count' => count($unresolved),
            ],
        ];
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Validate the GenerationResult produced by generateSlotPlan.
     *
     * Checks per section:
     *   1. No duplicate (day_of_week, start_time) within a section
     *   2. All placements pass constant constraints (H4–H12)
     *
     * @param  array $result  Output of generateSlotPlan
     * @return array{passes: bool, violations: list<string>}
     */
    public function validateResult(array $result): array
    {
        $violations = [];
        $grade      = (int) ($result['grade'] ?? 0);

        foreach ($result['sections'] ?? [] as $sectionName => $placements) {
            $seen = [];
            foreach ($placements as $p) {
                $key = ($p['day_of_week'] ?? '') . '|' . ($p['start_time'] ?? '');

                if (isset($seen[$key])) {
                    $violations[] = "{$sectionName}: duplicate slot {$key} for {$p['subject']} s{$p['session']}";
                }
                $seen[$key] = true;

                $check = HardConstraintChecker::checkAllConstantConstraints(
                    $grade,
                    $p['day_of_week'] ?? '',
                    $p['start_time']  ?? '',
                    $p['end_time']    ?? ''
                );
                if (! $check['passes']) {
                    $violations[] = "{$sectionName} {$p['subject']} s{$p['session']}: {$check['reason']}";
                }
            }
        }

        return [
            'passes'     => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Check that no two sections are scheduled for the SAME subject at the
     * SAME (day_of_week, start_time) — the anti-collision property.
     *
     * Elective and science_core placements are intentionally parallel (all
     * sections share the same slot for those types) and are excluded from
     * this check.  Only 'core' placements are validated.
     *
     * @param  array $result  Output of generateSlotPlan
     * @return array{passes: bool, violations: list<string>}
     */
    public function checkCrossSection(array $result): array
    {
        $violations = [];
        // Map of 'subject|day|start' → [sectionName, ...] for 'core' type only
        $subjectSlotOwners = [];

        foreach ($result['sections'] ?? [] as $sectionName => $placements) {
            foreach ($placements as $p) {
                if (($p['type'] ?? '') !== 'core') {
                    continue;
                }
                $key = ($p['subject'] ?? '') . '|' . ($p['day_of_week'] ?? '') . '|' . ($p['start_time'] ?? '');
                $subjectSlotOwners[$key][] = $sectionName;
            }
        }

        foreach ($subjectSlotOwners as $key => $owners) {
            if (count(array_unique($owners)) > 1) {
                [$subject, $day, $start] = explode('|', $key, 3);
                $sectionList  = implode(', ', array_unique($owners));
                $violations[] = "Subject '{$subject}' at {$day} {$start} assigned to multiple sections: {$sectionList}";
            }
        }

        return [
            'passes'     => empty($violations),
            'violations' => $violations,
        ];
    }

    // =========================================================================
    // Private placement helpers
    // =========================================================================

    /**
     * Place core subjects for G7–G10 using the rotation grid (or per-section
     * plans when elective windows must be avoided).
     *
     * When $reservedElectiveKeys is empty, the efficient grade-level plan is used.
     * When elective keys are present (G10), per-section plans are built so the
     * rotation grid skips those reserved windows.
     *
     * Mutates $sections and $unresolved in place.
     */
    private function placeJuniorCore(
        int   $grade,
        array $coreSubjects,
        array $reservedElectiveKeys,
        array &$sections,
        array &$unresolved
    ): void {
        if (empty($reservedElectiveKeys)) {
            // No elective windows to avoid — use efficient grade-level plan
            $plan = $this->corePlacer->buildGradePlan($grade, $coreSubjects);

            foreach ($plan['sections'] as $sectionName => $placements) {
                if (! isset($sections[$sectionName])) {
                    $sections[$sectionName] = [];
                }
                foreach ($placements as $p) {
                    $sections[$sectionName][] = $this->normalizeCorePlacement($p);
                }
            }

            foreach ($plan['unfilled'] as $u) {
                $unresolved[] = array_merge($u, ['type' => 'core']);
            }
            return;
        }

        // Elective windows must be avoided — build per-section plans
        $sectionNames = SchedulingConstants::GRADE_SECTIONS[$grade];
        foreach ($sectionNames as $idx => $sectionName) {
            if (! isset($sections[$sectionName])) {
                $sections[$sectionName] = [];
            }

            $placements = $this->corePlacer->buildSectionPlan(
                $grade, $idx, $coreSubjects, $reservedElectiveKeys
            );

            foreach ($placements as $p) {
                $sections[$sectionName][] = $this->normalizeCorePlacement($p);
            }

            // Track sessions that couldn't be placed (capacity check)
            $expectedSessions = array_sum(array_column($coreSubjects, 'sessions_per_week'));
            if (count($placements) < $expectedSessions) {
                $unresolved[] = [
                    'subject' => '(core overflow)',
                    'type'    => 'core',
                    'session' => count($placements) + 1,
                ];
            }
        }
    }

    /**
     * Place core subjects for G11–G12 using sequential slot scanning.
     * Each section gets independent slots (no rotation guarantee needed for SHS).
     * Mutates $sections and $unresolved in place.
     */
    private function placeSeniorCore(
        int   $grade,
        array $coreSubjects,
        array $sectionNames,
        array &$sections,
        array &$unresolved
    ): void {
        $allSlots = $this->getValidWeekSlots($grade);

        foreach ($sectionNames as $sectionName) {
            if (! isset($sections[$sectionName])) {
                $sections[$sectionName] = [];
            }

            // Build claimed-key list from already-placed slots for this section
            $claimed = $this->extractClaimedKeys($sections[$sectionName]);

            foreach ($coreSubjects as $subject) {
                $n    = max(1, (int) ($subject['sessions_per_week'] ?? 1));
                $name = (string) ($subject['name'] ?? '');

                for ($session = 1; $session <= $n; $session++) {
                    $placed = false;
                    foreach ($allSlots as $slot) {
                        $key = $slot['day'] . '|' . $slot['start'];
                        if (! in_array($key, $claimed, true)) {
                            $claimed[] = $key;
                            $sections[$sectionName][] = [
                                'subject'     => $name,
                                'type'        => 'core',
                                'session'     => $session,
                                'day_of_week' => $slot['day'],
                                'start_time'  => $slot['start'],
                                'end_time'    => $slot['end'],
                            ];
                            $placed = true;
                            break;
                        }
                    }
                    if (! $placed) {
                        $unresolved[] = ['subject' => $name, 'type' => 'core', 'session' => $session];
                    }
                }
            }
        }
    }

    /**
     * Merge a placement plan's 'sections' map into $sections, tagging each
     * entry with $type and normalizing key names to snake_case.
     */
    private function mergePlanIntoSections(
        array  $planSections,
        string $type,
        array  &$sections
    ): void {
        foreach ($planSections as $sectionName => $placements) {
            if (! isset($sections[$sectionName])) {
                $sections[$sectionName] = [];
            }
            foreach ($placements as $p) {
                $sections[$sectionName][] = [
                    'subject'     => $p['subject'],
                    'type'        => $type,
                    'session'     => $p['session'],
                    'day_of_week' => $p['day'],
                    'start_time'  => $p['start'],
                    'end_time'    => $p['end'],
                ];
            }
        }
    }

    /**
     * Normalize a CoreSubjectPlacementService placement (uses 'day'/'start'/'end')
     * to the generator's canonical shape (uses 'day_of_week'/'start_time'/'end_time').
     */
    private function normalizeCorePlacement(array $p): array
    {
        return [
            'subject'     => $p['subject'],
            'type'        => 'core',
            'session'     => $p['session'],
            'day_of_week' => $p['day'],
            'start_time'  => $p['start'],
            'end_time'    => $p['end'],
        ];
    }

    /**
     * Extract 'day_of_week|start_time' keys from an existing placement list.
     */
    private function extractClaimedKeys(array $placements): array
    {
        return array_map(
            fn ($p) => ($p['day_of_week'] ?? '') . '|' . ($p['start_time'] ?? ''),
            $placements
        );
    }

    /**
     * Return all CLASS slots for the full week that pass constant constraints.
     * No DB access — safe for pure-logic generation.
     *
     * @return list<array{day:string, start:string, end:string, label:string}>
     */
    private function getValidWeekSlots(int $grade): array
    {
        $slots = [];

        foreach (SchedulingConstants::DAYS as $day) {
            foreach (SchedulingConstants::getClassSlots($grade, $day) as $slot) {
                $check = HardConstraintChecker::checkAllConstantConstraints(
                    $grade, $day, $slot['start'], $slot['end']
                );
                if ($check['passes']) {
                    $slots[] = array_merge($slot, ['day' => $day]);
                }
            }
        }

        return $slots;
    }

    /**
     * Filter a subjects array by the 'type' key.
     */
    private function filterByType(array $subjects, string $type): array
    {
        return array_values(array_filter($subjects, fn ($s) => ($s['type'] ?? '') === $type));
    }
}
