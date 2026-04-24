<?php

namespace App\Services\FacultyLoading;

/**
 * HardConstraintChecker
 *
 * Named static checkers for every hard constraint the scheduler must honour.
 * Each method returns:
 *   ['passes' => true,  'reason' => null]            — constraint satisfied
 *   ['passes' => false, 'reason' => 'Human-readable explanation']  — violated
 *
 * Phases 3–8 add subject-placement logic; this class only validates individual
 * proposed placements.  Constraints that require DB lookups (H1, H2, H3, H14)
 * are implemented in Phase 9; pure-constant constraints live here and are safe
 * to call inside hot generation loops without any DB round-trips.
 *
 * Hard Constraint reference
 * ─────────────────────────
 *  H1  Teacher no-overlap          (Phase 9 — needs DB)
 *  H2  Section no-overlap          (Phase 9 — needs DB)
 *  H3  Teacher official-time       (Phase 9 — needs DB)
 *  H4  No class during FLAG        (checkFixedType / Phase 9)
 *  H5  No class during HOMEROOM    (checkFixedType / Phase 9)
 *  H6  No class during LUNCH       (checkFixedType / Phase 9)
 *  H7  No class during RECESS      (checkFixedType / Phase 9)
 *  H8  No class during CONSULT     (checkFixedType / Phase 9)
 *  H9  G7/G8 Monday dead zone      (checkMondayDeadZone — Phase 4)
 *  H10 Wednesday Activity lock     (checkWednesdayActivityLock — Phase 4)
 *  H11 Wednesday Wellness block    (checkWednesdayWellness — Phase 4)
 *  H12 G7/G8 Friday ILA            (checkFridayILA — Phase 4)
 *  H13 Unit-load completion        (Phase 8 — full placement)
 *  H14 Room no-overlap             (Phase 9 — needs DB)
 *  H15 Science Core parallel       (Phase 6)
 */
class HardConstraintChecker
{
    // ── Result helpers ────────────────────────────────────────────────────────

    /** @return array{passes: true, reason: null} */
    private static function ok(): array
    {
        return ['passes' => true, 'reason' => null];
    }

    /** @return array{passes: false, reason: string} */
    private static function fail(string $reason): array
    {
        return ['passes' => false, 'reason' => $reason];
    }

    // =========================================================================
    // H9 — G7/G8 Monday dead zone (08:50–09:40)
    // =========================================================================

    /**
     * H9: G7 and G8 have a dead zone on Monday from 08:50 to 09:40 (no classes).
     *
     * @param  int    $grade      7–12
     * @param  string $day        Day of week
     * @param  string $classStart HH:MM
     * @param  string $classEnd   HH:MM
     */
    public static function checkMondayDeadZone(
        int    $grade,
        string $day,
        string $classStart,
        string $classEnd
    ): array {
        if ($day !== 'Monday') {
            return self::ok();
        }

        if ($grade > 8) {
            return self::ok();
        }

        $deadStart = '08:50';
        $deadEnd   = '09:40';

        if (SchedulingConstants::timesOverlap($classStart, $classEnd, $deadStart, $deadEnd)) {
            return self::fail(
                "H9: G{$grade} Monday dead zone {$deadStart}–{$deadEnd} — "
                . "no classes allowed in this window ({$classStart}–{$classEnd} overlaps)"
            );
        }

        return self::ok();
    }

    // =========================================================================
    // H10 — Wednesday Activity lock
    // =========================================================================

    /**
     * H10: No class may start at or after the Wednesday Activity Start time
     * for the grade group.
     *
     *   G7/G8   → 15:00
     *   G9/G10  → 15:00
     *   G11/G12 → 12:20
     *
     * @param  int    $grade      7–12
     * @param  string $day        Day of week
     * @param  string $classStart HH:MM proposed start
     */
    public static function checkWednesdayActivityLock(
        int    $grade,
        string $day,
        string $classStart
    ): array {
        if ($day !== 'Wednesday') {
            return self::ok();
        }

        $gradeGroup  = SchedulingConstants::getGradeGroup($grade);
        $actStart    = SchedulingConstants::WEDNESDAY_ACTIVITY_START[$gradeGroup];

        if ($classStart >= $actStart) {
            return self::fail(
                "H10: G{$grade} Wednesday Activity lock — classes must end by {$actStart} "
                . "(proposed start {$classStart} is at or after the Activity Proper window)"
            );
        }

        return self::ok();
    }

    /**
     * H10 (end variant): Also blocks any class whose end time extends past the
     * activity start, even if it begins before it.
     *
     * @param  string $classEnd  HH:MM proposed end
     */
    public static function checkWednesdayActivityLockEnd(
        int    $grade,
        string $day,
        string $classStart,
        string $classEnd
    ): array {
        if ($day !== 'Wednesday') {
            return self::ok();
        }

        $gradeGroup = SchedulingConstants::getGradeGroup($grade);
        $actStart   = SchedulingConstants::WEDNESDAY_ACTIVITY_START[$gradeGroup];

        // Class must be fully contained before the activity lock
        if ($classEnd > $actStart) {
            return self::fail(
                "H10: G{$grade} Wednesday Activity lock — class {$classStart}–{$classEnd} "
                . "runs into or past the Activity Proper window (starts at {$actStart})"
            );
        }

        return self::ok();
    }

    // =========================================================================
    // H11 — Wednesday Wellness block (09:50–10:20)
    // =========================================================================

    /**
     * H11: No class slot may overlap the 30-min Wednesday Wellness block.
     * The wellness block is 09:50–10:20 and applies to all grades.
     *
     * @param  string $day        Day of week
     * @param  string $classStart HH:MM
     * @param  string $classEnd   HH:MM
     */
    public static function checkWednesdayWellness(
        string $day,
        string $classStart,
        string $classEnd
    ): array {
        if ($day !== 'Wednesday') {
            return self::ok();
        }

        $wStart = SchedulingConstants::WEDNESDAY_WELLNESS['start']; // 09:50
        $wEnd   = SchedulingConstants::WEDNESDAY_WELLNESS['end'];   // 10:20

        if (SchedulingConstants::timesOverlap($classStart, $classEnd, $wStart, $wEnd)) {
            return self::fail(
                "H11: Wednesday Wellness block {$wStart}–{$wEnd} — "
                . "proposed class {$classStart}–{$classEnd} overlaps this protected window"
            );
        }

        return self::ok();
    }

    // =========================================================================
    // H12 — G7/G8 Friday ILA day
    // =========================================================================

    /**
     * H12: Grades 7 and 8 have no in-person teaching classes on Fridays.
     * The entire day is reserved for Independent Learning Activities.
     *
     * @param  int    $grade  7–12
     * @param  string $day    Day of week
     */
    public static function checkFridayILA(int $grade, string $day): array
    {
        if ($day !== 'Friday') {
            return self::ok();
        }

        if (! in_array($grade, SchedulingConstants::FRIDAY_ILA_GRADES, true)) {
            return self::ok();
        }

        return self::fail(
            "H12: Grade {$grade} Friday ILA — no in-person classes may be scheduled "
            . 'on Fridays for G7/G8 (Independent Learning Activities day)'
        );
    }

    // =========================================================================
    // Composite: all special-day locks in one call
    // =========================================================================

    /**
     * Run H9 + H10 + H11 + H12 in sequence.
     * Returns the first violation found, or a passing result.
     *
     * This is the call the generator makes before placing any CLASS slot.
     *
     * @param  int    $grade
     * @param  string $day
     * @param  string $classStart  HH:MM
     * @param  string $classEnd    HH:MM
     * @return array{passes: bool, reason: string|null}
     */
    public static function checkSpecialDayLocks(
        int    $grade,
        string $day,
        string $classStart,
        string $classEnd
    ): array {
        $checks = [
            self::checkMondayDeadZone($grade, $day, $classStart, $classEnd),
            self::checkWednesdayActivityLockEnd($grade, $day, $classStart, $classEnd),
            self::checkWednesdayWellness($day, $classStart, $classEnd),
            self::checkFridayILA($grade, $day),
        ];

        foreach ($checks as $result) {
            if (! $result['passes']) {
                return $result;
            }
        }

        return self::ok();
    }

    // =========================================================================
    // Utility: check a proposed slot against ALL fixed types (H4–H9)
    // =========================================================================

    /**
     * Check that a proposed CLASS window does not overlap any fixed (non-class)
     * slot for the grade and day, using SchedulingConstants (no DB).
     *
     * Covers H4 (FLAG), H5 (HOMEROOM/ADVISING), H6 (LUNCH), H7 (RECESS),
     * H8 (CONSULT), H9 (DEAD).
     *
     * @param  int    $grade
     * @param  string $day
     * @param  string $classStart
     * @param  string $classEnd
     */
    public static function checkFixedSlotOverlap(
        int    $grade,
        string $day,
        string $classStart,
        string $classEnd
    ): array {
        $gradeGroup = SchedulingConstants::getGradeGroup($grade);
        $blocked    = FixedSlotService::fixedSlotsForDay($grade, $gradeGroup, $day);

        foreach ($blocked as $slot) {
            if (SchedulingConstants::timesOverlap($classStart, $classEnd, $slot['start'], $slot['end'])) {
                $label = $slot['label'] ?: $slot['type'];
                $code = self::constraintCodeForType($slot['type']);
                return self::fail(
                    "H{$code}: Class {$classStart}–{$classEnd} overlaps fixed slot "
                    . "{$slot['type']} ({$slot['start']}–{$slot['end']}) — {$label}"
                );
            }
        }

        return self::ok();
    }

    /**
     * Full special-day + fixed-slot check in one call.
     * Covers H4–H12 (all pure-constant constraints).
     */
    public static function checkAllConstantConstraints(
        int    $grade,
        string $day,
        string $classStart,
        string $classEnd
    ): array {
        $result = self::checkSpecialDayLocks($grade, $day, $classStart, $classEnd);
        if (! $result['passes']) {
            return $result;
        }

        return self::checkFixedSlotOverlap($grade, $day, $classStart, $classEnd);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function constraintCodeForType(string $type): string
    {
        return match ($type) {
            'FLAG'     => '4',
            'HOMEROOM', 'ADVISING' => '5',
            'LUNCH'    => '6',
            'RECESS'   => '7',
            'CONSULT'  => '8',
            'DEAD'     => '9',
            'WELLNESS' => '11',
            'ACTIVITY' => '10',
            'ILA'      => '12',
            default    => '?',
        };
    }
}
