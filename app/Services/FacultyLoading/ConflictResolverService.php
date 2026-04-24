<?php

namespace App\Services\FacultyLoading;

/**
 * ConflictResolverService
 *
 * Phase 10 — Finds a conflict-free alternative when the generator cannot
 * place a subject session at its initially suggested slot.
 *
 * Resolution strategies
 * ─────────────────────
 *  slot_bump  — Move to the next available CLASS slot.  Used when the
 *               violation is time-based: H1 (teacher overlap), H2 (section
 *               overlap), H3 (official time), or any constant constraint
 *               (H4–H12).
 *
 *  room_swap  — Keep the same (day, start, end) but try an alternative
 *               classroom.  Only attempted when ALL violations are H14
 *               (room overlap).  Falls back to slot_bump if no room works.
 *
 * This service is pure scheduling logic — it does not write to the DB.
 * All constraint checking is delegated to FullConstraintValidator.
 *
 * Typical usage (inside the generator loop):
 * ──────────────────────────────────────────
 *   $result = $validator->check($proposal);
 *   if (! $result['passes']) {
 *       $resolved = $resolver->resolve(
 *           proposal:         $proposal,
 *           violations:       $result['violations'],
 *           candidateSlots:   $weeklySlots,
 *           excludeSlotKeys:  $alreadyClaimed,
 *           alternativeRooms: $roomIds,
 *       );
 *       // $resolved is null if no alternative found
 *   }
 */
class ConflictResolverService
{
    public const STRATEGY_SLOT_BUMP    = 'slot_bump';
    public const STRATEGY_ROOM_SWAP    = 'room_swap';
    public const STRATEGY_UNRESOLVABLE = 'unresolvable';

    public function __construct(
        private readonly FullConstraintValidator $validator
    ) {}

    // =========================================================================
    // Primary entry point
    // =========================================================================

    /**
     * Resolve a failing proposal using the appropriate strategy.
     *
     * Resolution order:
     *   1. If ALL violations are H14 and alternativeRooms given → try room_swap.
     *   2. If room_swap fails (or is not applicable) → try slot_bump.
     *   3. If slot_bump exhausts all candidates → return null.
     *
     * @param  array    $proposal           Failing proposal (FullConstraintValidator shape)
     * @param  array    $violations         Output of FullConstraintValidator::check violations
     * @param  array    $candidateSlots     Ordered CLASS slots to try (from RotationGridBuilder etc.)
     * @param  array    $excludeSlotKeys    Already-claimed 'day|start' keys to skip
     * @param  array    $alternativeRooms   Classroom IDs to try for H14 resolution
     * @param  int|null $excludeScheduleId  Skip this schedule row (update scenario)
     * @return array|null  Resolved proposal or null if unresolvable
     */
    public function resolve(
        array  $proposal,
        array  $violations,
        array  $candidateSlots,
        array  $excludeSlotKeys   = [],
        array  $alternativeRooms  = [],
        ?int   $excludeScheduleId = null
    ): ?array {
        $strategy = $this->getResolutionStrategy($violations);

        if ($strategy === self::STRATEGY_UNRESOLVABLE) {
            return null;
        }

        // Room swap — only when all violations are H14 and alternatives are given
        if ($strategy === self::STRATEGY_ROOM_SWAP && ! empty($alternativeRooms)) {
            $resolved = $this->resolveRoomSwap($proposal, $alternativeRooms, $excludeScheduleId);
            if ($resolved !== null) {
                return $resolved;
            }
            // Room swap exhausted; fall through to slot bump
        }

        return $this->resolveSlotBump($proposal, $candidateSlots, $excludeSlotKeys, $excludeScheduleId);
    }

    // =========================================================================
    // Strategy selection
    // =========================================================================

    /**
     * Determine the resolution strategy from a violations list.
     *
     * Rules (in priority order):
     *   • Empty violations        → 'unresolvable' (nothing to resolve)
     *   • ALL codes are 'H14'    → 'room_swap'
     *   • Any other code present → 'slot_bump'
     *
     * @param  array $violations  list<array{code: string, reason: string}>
     * @return string  One of the STRATEGY_* constants
     */
    public function getResolutionStrategy(array $violations): string
    {
        if (empty($violations)) {
            return self::STRATEGY_UNRESOLVABLE;
        }

        $codes    = array_column($violations, 'code');
        $h14Count = count(array_filter($codes, static fn ($c) => $c === 'H14'));

        // All violations are room conflicts → try a room swap first
        if ($h14Count === count($codes)) {
            return self::STRATEGY_ROOM_SWAP;
        }

        return self::STRATEGY_SLOT_BUMP;
    }

    // =========================================================================
    // Slot bump
    // =========================================================================

    /**
     * Iterate $candidateSlots, skipping the failing slot and any excluded keys,
     * and return the first slot that passes all constraints (constant + DB).
     *
     * The failing slot (from the proposal) is automatically added to the
     * exclusion list so it is never retried.
     *
     * @param  array    $proposal
     * @param  array    $candidateSlots   Ordered list of {day, start, end, ...} slots
     * @param  array    $excludeSlotKeys  'day|start' strings to skip
     * @param  int|null $excludeScheduleId
     * @return array|null  The resolved proposal with updated day/start/end, or null
     */
    public function resolveSlotBump(
        array  $proposal,
        array  $candidateSlots,
        array  $excludeSlotKeys   = [],
        ?int   $excludeScheduleId = null
    ): ?array {
        $grade      = (int) ($proposal['grade'] ?? 0);
        $currentKey = ($proposal['day_of_week'] ?? '') . '|' . ($proposal['start_time'] ?? '');

        // Always exclude the current failing slot
        $excluded = array_unique(array_merge($excludeSlotKeys, [$currentKey]));

        foreach ($candidateSlots as $slot) {
            $key = $slot['day'] . '|' . $slot['start'];

            if (in_array($key, $excluded, true)) {
                continue;
            }

            $newProposal = array_merge($proposal, [
                'day_of_week' => $slot['day'],
                'start_time'  => $slot['start'],
                'end_time'    => $slot['end'],
            ]);

            $result = $this->validator->check($newProposal, $excludeScheduleId);
            if ($result['passes']) {
                return $newProposal;
            }

            // If this new slot also fails, add it to exclusions and keep going
            $excluded[] = $key;
        }

        return null;
    }

    // =========================================================================
    // Room swap
    // =========================================================================

    /**
     * Keep the same (day, start, end) but try each alternative classroom.
     *
     * Skips the current classroom ID (don't retry the same room).
     * Returns the first proposal with an alternative room that passes all
     * constraints, or null if none work.
     *
     * @param  array    $proposal
     * @param  array    $alternativeRooms  Classroom IDs to try  (int[])
     * @param  int|null $excludeScheduleId
     * @return array|null
     */
    public function resolveRoomSwap(
        array  $proposal,
        array  $alternativeRooms,
        ?int   $excludeScheduleId = null
    ): ?array {
        $currentRoom = (int) ($proposal['classroom_id'] ?? 0);

        foreach ($alternativeRooms as $roomId) {
            $roomId = (int) $roomId;

            // Skip the room that already failed
            if ($roomId === $currentRoom) {
                continue;
            }

            $newProposal = array_merge($proposal, ['classroom_id' => $roomId]);
            $result      = $this->validator->check($newProposal, $excludeScheduleId);

            if ($result['passes']) {
                return $newProposal;
            }
        }

        return null;
    }

    // =========================================================================
    // Constant-only slot finder (no DB — for hot generation loops)
    // =========================================================================

    /**
     * Scan $candidateSlots and return the first slot that:
     *   1. Is not in $excludeSlotKeys
     *   2. Passes all constant constraints (H4–H12) for the grade
     *
     * No DB access — safe to call frequently inside tight loops.
     * Use resolveSlotBump when full DB constraint checking is needed.
     *
     * @param  int    $grade
     * @param  array  $candidateSlots  Ordered list of {day, start, end, ...} slots
     * @param  array  $excludeSlotKeys 'day|start' strings to skip
     * @return array|null
     */
    public function findNextValidSlot(
        int   $grade,
        array $candidateSlots,
        array $excludeSlotKeys = []
    ): ?array {
        foreach ($candidateSlots as $slot) {
            $key = $slot['day'] . '|' . $slot['start'];

            if (in_array($key, $excludeSlotKeys, true)) {
                continue;
            }

            $check = HardConstraintChecker::checkAllConstantConstraints(
                $grade, $slot['day'], $slot['start'], $slot['end']
            );

            if ($check['passes']) {
                return $slot;
            }
        }

        return null;
    }

    // =========================================================================
    // Diagnostic helpers
    // =========================================================================

    /**
     * Return a human-readable summary of which constraints violated and why.
     * Useful for logging during generation runs.
     *
     * @param  array $violations  list<array{code: string, reason: string}>
     * @return string
     */
    public function describeViolations(array $violations): string
    {
        if (empty($violations)) {
            return 'No violations.';
        }

        return implode(' | ', array_map(
            static fn ($v) => "[{$v['code']}] {$v['reason']}",
            $violations
        ));
    }
}
