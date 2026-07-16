<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\TeacherOfficialTime;

/**
 * FullConstraintValidator
 *
 * Phase 9 — Unified hard-constraint gate for the schedule generator.
 *
 * Combines every hard constraint (H1–H15) into a single check() call that
 * the generator invokes before committing any slot placement.
 *
 * Constraint classification
 * ─────────────────────────
 *  Constant (pure-logic, no DB — covered by HardConstraintChecker):
 *    H4   No class during FLAG
 *    H5   No class during HOMEROOM / ADVISING
 *    H6   No class during LUNCH
 *    H7   No class during RECESS
 *    H8   No class during CONSULT / ILA
 *    H9   G7 Monday dead zone (08:50–09:40)
 *    H10  Wednesday Activity lock
 *    H11  Wednesday Wellness block (09:50–10:20)
 *    H12  Configured Friday ILA
 *
 *  DB-based (need live records):
 *    H1   Teacher (faculty) no-overlap
 *    H2   Section no-overlap
 *    H3   Teacher official-time respect
 *    H14  Room (classroom) no-overlap
 *
 *  Handled by other services (not re-checked here):
 *    H13  Unit-load completion  (ScheduleValidationService / LoadComputationService)
 *    H15  Science Core parallel (ScienceCorePlacementService::validateH15)
 *
 * Proposal shape (input to check / checkDB):
 * {
 *   grade:            int,       // 7–12  (required for constant checks)
 *   day_of_week:      string,    // 'Monday' … 'Friday'
 *   start_time:       string,    // 'HH:MM'
 *   end_time:         string,    // 'HH:MM'
 *   academic_term_id: int|null,  // required for H1, H2, H14
 *   faculty_id:       int|null,  // H1, H3
 *   section_id:       int|null,  // H2
 *   classroom_id:     int|null,  // H14
 * }
 *
 * Result shape:
 * {
 *   passes:     bool,
 *   violations: [
 *     { code: 'H1', reason: 'H1: Teacher conflict…' },
 *     { code: 'H9', reason: 'H9: Monday dead zone…' },
 *     …
 *   ]
 * }
 */
class FullConstraintValidator
{
    public function __construct(
        private readonly ConflictDetectionService $conflicts
    ) {}

    // =========================================================================
    // Primary entry point
    // =========================================================================

    /**
     * Run all applicable hard constraints for a proposed schedule slot.
     *
     * Constant constraints (H4–H12) are checked first; on the first constant
     * violation the function still continues to collect DB violations so the
     * caller receives a complete picture.
     *
     * DB constraints (H1, H2, H3, H14) are only run when the corresponding
     * ID fields are present in $proposal.
     *
     * @param  array    $proposal          See class docblock for shape
     * @param  int|null $excludeScheduleId ClassSchedule id to ignore (for updates)
     * @return array{passes: bool, violations: list<array{code: string, reason: string}>}
     */
    public function check(array $proposal, ?int $excludeScheduleId = null): array
    {
        $grade = (int) ($proposal['grade']      ?? 0);
        $day   = (string) ($proposal['day_of_week'] ?? '');
        $start = (string) ($proposal['start_time']  ?? '');
        $end   = (string) ($proposal['end_time']    ?? '');

        $violations = [];

        // ── Constant constraints (H4–H12) ────────────────────────────────────
        $constantResult = $this->checkConstant($grade, $day, $start, $end);
        $violations     = array_merge($violations, $constantResult['violations']);

        // ── DB-based constraints (H1, H2, H3, H14) ──────────────────────────
        $dbResult   = $this->checkDB($proposal, $excludeScheduleId);
        $violations = array_merge($violations, $dbResult['violations']);

        return [
            'passes'     => empty($violations),
            'violations' => $violations,
        ];
    }

    // =========================================================================
    // Constant constraints (H4–H12)
    // =========================================================================

    /**
     * Run all pure-constant constraints (H4–H12) for a proposed slot.
     *
     * No DB access — safe to call inside hot generation loops.
     * Returns all violations found (does NOT short-circuit).
     *
     * @param  int    $grade
     * @param  string $day
     * @param  string $start  HH:MM
     * @param  string $end    HH:MM
     * @return array{passes: bool, violations: list<array{code: string, reason: string}>}
     */
    public function checkConstant(int $grade, string $day, string $start, string $end): array
    {
        $violations = [];

        // H9–H12: Special day locks (Monday dead zone, Wednesday locks, Friday ILA)
        $specialResult = HardConstraintChecker::checkSpecialDayLocks($grade, $day, $start, $end);
        if (! $specialResult['passes']) {
            $violations[] = [
                'code'   => $this->codeFromReason($specialResult['reason'] ?? ''),
                'reason' => $specialResult['reason'],
            ];
        }

        // H4–H8: Fixed slot overlap (FLAG, HOMEROOM, LUNCH, RECESS, CONSULT…)
        $fixedResult = HardConstraintChecker::checkFixedSlotOverlap($grade, $day, $start, $end);
        if (! $fixedResult['passes']) {
            $violations[] = [
                'code'   => $this->codeFromReason($fixedResult['reason'] ?? ''),
                'reason' => $fixedResult['reason'],
            ];
        }

        return [
            'passes'     => empty($violations),
            'violations' => $violations,
        ];
    }

    // =========================================================================
    // DB-based constraints (H1, H2, H3, H14)
    // =========================================================================

    /**
     * Run all DB-based constraints for a proposed slot.
     *
     * Each constraint is checked independently; all violations are collected.
     * A constraint is skipped when its required proposal key is absent or null.
     *
     * @param  array    $proposal
     * @param  int|null $excludeScheduleId
     * @return array{passes: bool, violations: list<array{code: string, reason: string}>}
     */
    public function checkDB(array $proposal, ?int $excludeScheduleId = null): array
    {
        $violations = [];

        $day    = (string) ($proposal['day_of_week'] ?? '');
        $start  = (string) ($proposal['start_time']  ?? '');
        $end    = (string) ($proposal['end_time']    ?? '');
        $termId = isset($proposal['academic_term_id']) ? (int) $proposal['academic_term_id'] : null;

        // H1: Teacher (faculty) no-overlap
        if (! empty($proposal['faculty_id']) && $termId !== null) {
            $violations = array_merge(
                $violations,
                $this->checkH1((int) $proposal['faculty_id'], $day, $start, $end, $termId, $excludeScheduleId)
            );
        }

        // H2: Section no-overlap
        if (! empty($proposal['section_id']) && $termId !== null) {
            $violations = array_merge(
                $violations,
                $this->checkH2((int) $proposal['section_id'], $day, $start, $end, $termId, $excludeScheduleId)
            );
        }

        // H3: Teacher official-time respect
        if (! empty($proposal['faculty_id'])) {
            $violations = array_merge(
                $violations,
                $this->checkH3((int) $proposal['faculty_id'], $day, $start, $end)
            );
        }

        // H14: Room (classroom) no-overlap
        if (! empty($proposal['classroom_id']) && $termId !== null) {
            $violations = array_merge(
                $violations,
                $this->checkH14((int) $proposal['classroom_id'], $day, $start, $end, $termId, $excludeScheduleId)
            );
        }

        return [
            'passes'     => empty($violations),
            'violations' => $violations,
        ];
    }

    // =========================================================================
    // Individual DB constraint methods
    // =========================================================================

    /**
     * H1: Teacher no-overlap.
     *
     * Fires when the faculty member already has an active schedule on the same
     * day that overlaps the proposed (start, end).
     *
     * @return list<array{code: string, reason: string}>
     */
    public function checkH1(
        int    $facultyId,
        string $day,
        string $start,
        string $end,
        int    $termId,
        ?int   $excludeId = null
    ): array {
        $conflicts  = $this->conflicts->detectFacultyConflict($facultyId, $day, $start, $end, $termId, $excludeId);
        $violations = [];

        foreach ($conflicts as $conflict) {
            $subjectName = $conflict->subject?->name ?? '(unknown subject)';
            $violations[] = [
                'code'   => 'H1',
                'reason' => "H1: Teacher conflict on {$day} {$start}–{$end} — "
                    . "already teaching '{$subjectName}' "
                    . "{$conflict->start_time}–{$conflict->end_time}",
            ];
        }

        return $violations;
    }

    /**
     * H2: Section no-overlap.
     *
     * Fires when the section already has an active schedule on the same day
     * that overlaps the proposed (start, end).
     *
     * @return list<array{code: string, reason: string}>
     */
    public function checkH2(
        int    $sectionId,
        string $day,
        string $start,
        string $end,
        int    $termId,
        ?int   $excludeId = null
    ): array {
        $conflicts  = $this->conflicts->detectSectionConflict($sectionId, $day, $start, $end, $termId, $excludeId);
        $violations = [];

        foreach ($conflicts as $conflict) {
            $subjectName = $conflict->subject?->name ?? '(unknown subject)';
            $violations[] = [
                'code'   => 'H2',
                'reason' => "H2: Section conflict on {$day} {$start}–{$end} — "
                    . "section already has '{$subjectName}' "
                    . "{$conflict->start_time}–{$conflict->end_time}",
            ];
        }

        return $violations;
    }

    /**
     * H3: Teacher official-time respect.
     *
     * The proposed class window must fit within the teacher's official working
     * hours for that day.  Falls back to SHIFT_EARLY (07:30–16:30) when no
     * TeacherOfficialTime record exists for that teacher+day combination.
     *
     * @return list<array{code: string, reason: string}>
     */
    public function checkH3(
        int    $facultyId,
        string $day,
        string $start,
        string $end
    ): array {
        $ot = TeacherOfficialTime::where('user_id', $facultyId)
            ->where('day_of_week', $day)
            ->first();

        if ($ot !== null) {
            $otStart = substr($ot->start_time, 0, 5);
            $otEnd   = substr($ot->end_time,   0, 5);
        } else {
            // Default: early shift
            $otStart = SchedulingConstants::SHIFT_EARLY['start'];
            $otEnd   = SchedulingConstants::SHIFT_EARLY['end'];
        }

        if ($start < $otStart || $end > $otEnd) {
            return [[
                'code'   => 'H3',
                'reason' => "H3: Class {$start}–{$end} on {$day} is outside teacher's "
                    . "official time ({$otStart}–{$otEnd})",
            ]];
        }

        return [];
    }

    /**
     * H14: Room (classroom) no-overlap.
     *
     * Fires when the classroom already has an active schedule on the same day
     * that overlaps the proposed (start, end).
     *
     * @return list<array{code: string, reason: string}>
     */
    public function checkH14(
        int    $classroomId,
        string $day,
        string $start,
        string $end,
        int    $termId,
        ?int   $excludeId = null
    ): array {
        $conflicts  = $this->conflicts->detectRoomConflict($classroomId, $day, $start, $end, $termId, $excludeId);
        $violations = [];

        foreach ($conflicts as $conflict) {
            $subjectName = $conflict->subject?->name ?? '(unknown subject)';
            $violations[] = [
                'code'   => 'H14',
                'reason' => "H14: Room conflict on {$day} {$start}–{$end} — "
                    . "classroom already booked for '{$subjectName}' "
                    . "{$conflict->start_time}–{$conflict->end_time}",
            ];
        }

        return $violations;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Extract the Hxx code from the beginning of a reason string.
     * e.g. "H9: Monday dead zone…" → "H9"
     */
    private function codeFromReason(string $reason): string
    {
        if (preg_match('/^(H\d+):/i', $reason, $m)) {
            return strtoupper($m[1]);
        }

        return 'H?';
    }
}
