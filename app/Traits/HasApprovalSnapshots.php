<?php

namespace App\Traits;

use App\Models\ApprovalSnapshot;
use App\Models\SignatorySnapshot;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Add to any model that participates in an approval workflow.
 *
 * Usage:
 *   class LeaveApplication extends Model
 *   {
 *       use HasApprovalSnapshots;
 *   }
 */
trait HasApprovalSnapshots
{
    // ── Relationships ──────────────────────────────────────────────────────────

    /** All approval snapshots for this record, ordered by sequence. */
    public function approvalSnapshots(): MorphMany
    {
        return $this->morphMany(ApprovalSnapshot::class, 'approvable')
                    ->orderBy('sequence');
    }

    /** All signatory snapshots for this record (for printed forms). */
    public function signatorySnapshots(): MorphMany
    {
        return $this->morphMany(SignatorySnapshot::class, 'signable');
    }

    // ── Lookup Helpers ─────────────────────────────────────────────────────────

    /**
     * Return the snapshot for a specific step, or null if not yet recorded.
     * Uses the already-loaded collection if available to avoid extra queries.
     */
    public function snapshotForStep(string $step): ?ApprovalSnapshot
    {
        if ($this->relationLoaded('approvalSnapshots')) {
            return $this->approvalSnapshots->firstWhere('step', $step);
        }

        return $this->approvalSnapshots()->where('step', $step)->first();
    }

    /**
     * Return the signatory snapshot for a specific role label, or null.
     */
    public function signatoryForRole(string $roleLabel): ?SignatorySnapshot
    {
        if ($this->relationLoaded('signatorySnapshots')) {
            return $this->signatorySnapshots->firstWhere('role_label', $roleLabel);
        }

        return $this->signatorySnapshots()->where('role_label', $roleLabel)->first();
    }

    /**
     * Resolve display data for a step — snapshot first, live FK fallback.
     *
     * Returns an array: [name, position, division, office]
     * Never returns null — always falls back gracefully.
     *
     * @param string      $step       e.g. 'hr_officer', 'division_chief'
     * @param string|null $fkColumn   e.g. 'hr_officer_id', 'approved_by'
     */
    public function snapshotOrLive(string $step, ?string $fkColumn = null): array
    {
        // ── 1. Try snapshot first ──────────────────────────────────────────────
        $snap = $this->snapshotForStep($step);

        if ($snap) {
            return [
                'name'     => $snap->name_snapshot,
                'position' => $snap->position_snapshot,
                'division' => $snap->division_snapshot,
                'office'   => $snap->office_snapshot,
                'source'   => 'snapshot',
            ];
        }

        // ── 2. Fall back to live FK ────────────────────────────────────────────
        $fkColumn ??= $step . '_id';
        $userId     = $this->{$fkColumn} ?? null;

        if (! $userId) {
            return ['name' => null, 'position' => null, 'division' => null, 'office' => null, 'source' => 'none'];
        }

        $user = \App\Models\User::with(['division', 'office'])->find($userId);

        if (! $user) {
            return ['name' => null, 'position' => null, 'division' => null, 'office' => null, 'source' => 'none'];
        }

        return [
            'name'     => $user->name,
            'position' => $user->position,
            'division' => $user->division?->division_name,
            'office'   => $user->office?->name,
            'source'   => 'live',
        ];
    }
}
