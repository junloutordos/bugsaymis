<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Support\Facades\DB;

/**
 * SchoolYearWorkflowService
 *
 * Manages the lifecycle state machine for school years:
 *
 *   draft → proposed → approved → published → archived
 *
 * Transition rules:
 *   - draft      → proposed  : any time; no other school year needs to be active
 *   - proposed   → approved  : only by authorized reviewer (enforced in controller)
 *   - approved   → published : sets is_current = true; demotes previous current year
 *   - any state  → archived  : only if no active faculty loads exist for the year
 *
 * Only one school year may be in `published` + `is_current = true` state at a time.
 */
class SchoolYearWorkflowService
{
    private const TRANSITIONS = [
        'draft'     => 'proposed',
        'proposed'  => 'approved',
        'approved'  => 'published',
        'published' => 'archived',
    ];

    // ── Transition ────────────────────────────────────────────────────────────

    /**
     * Advance the school year to the next workflow state.
     * Returns ['ok' => bool, 'message' => string, 'school_year' => SchoolYear].
     */
    public function advance(SchoolYear $schoolYear): array
    {
        $from = $schoolYear->workflow_status;
        $to   = self::TRANSITIONS[$from] ?? null;

        if (! $to) {
            return $this->fail("Cannot advance from '{$from}' — no further states.", $schoolYear);
        }

        $check = $this->canTransition($schoolYear, $to);
        if (! $check['ok']) {
            return $check;
        }

        return DB::transaction(function () use ($schoolYear, $to) {
            if ($to === 'published') {
                $this->demoteCurrentYear();
                $schoolYear->update([
                    'workflow_status' => 'published',
                    'is_current'      => true,
                ]);
            } else {
                $schoolYear->update(['workflow_status' => $to]);
            }

            return [
                'ok'          => true,
                'message'     => "School year advanced to '{$to}'.",
                'school_year' => $schoolYear->fresh(),
            ];
        });
    }

    /**
     * Archive a school year directly (skips normal transition order).
     * Allowed from any non-archived state, provided no active loads exist.
     */
    public function archive(SchoolYear $schoolYear): array
    {
        if ($schoolYear->workflow_status === 'archived') {
            return $this->fail('School year is already archived.', $schoolYear);
        }

        if ($this->hasActiveFacultyLoads($schoolYear)) {
            return $this->fail(
                'Cannot archive: active faculty load records exist for this school year.',
                $schoolYear
            );
        }

        $schoolYear->update([
            'workflow_status' => 'archived',
            'is_current'      => false,
        ]);

        return [
            'ok'          => true,
            'message'     => 'School year archived.',
            'school_year' => $schoolYear->fresh(),
        ];
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Check whether a transition to $targetStatus is allowed.
     */
    public function canTransition(SchoolYear $schoolYear, string $targetStatus): array
    {
        if ($targetStatus === 'published') {
            // Only one published year at a time — the old one gets demoted, not blocked.
            // Nothing to block here; demotion is handled in advance().
        }

        if ($targetStatus === 'archived') {
            if ($this->hasActiveFacultyLoads($schoolYear)) {
                return $this->fail(
                    'Cannot archive: active faculty load records exist for this school year.',
                    $schoolYear
                );
            }
        }

        return ['ok' => true, 'message' => "Transition to '{$targetStatus}' is allowed.", 'school_year' => $schoolYear];
    }

    /**
     * Return a human-readable list of valid next states for UI display.
     */
    public function availableTransitions(SchoolYear $schoolYear): array
    {
        $transitions = [];
        $next = self::TRANSITIONS[$schoolYear->workflow_status] ?? null;

        if ($next) {
            $transitions[] = $next;
        }

        // Archiving is always available (if loads allow) unless already archived
        if ($schoolYear->workflow_status !== 'archived' && $next !== 'archived') {
            $transitions[] = 'archived';
        }

        return $transitions;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function demoteCurrentYear(): void
    {
        SchoolYear::where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function hasActiveFacultyLoads(SchoolYear $schoolYear): bool
    {
        return $schoolYear->facultyLoads()
            ->whereIn('load_status', ['full_load', 'underload', 'overload'])
            ->exists();
    }

    private function fail(string $message, SchoolYear $schoolYear): array
    {
        return ['ok' => false, 'message' => $message, 'school_year' => $schoolYear];
    }
}
