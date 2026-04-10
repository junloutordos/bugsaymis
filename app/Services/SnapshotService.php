<?php

namespace App\Services;

use App\Models\ApprovalSnapshot;
use App\Models\SignatorySnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * SnapshotService
 *
 * The ONLY class that writes to approval_snapshots and signatory_snapshots.
 * Controllers and other services must go through this class — never write
 * snapshot rows directly.
 *
 * All writes are idempotent:
 *   - recordApproval()    → skips silently if snapshot already exists for that step
 *   - captureSignatories() → skips per role_label if already captured
 */
class SnapshotService
{
    // ── User Data Resolution ───────────────────────────────────────────────────

    /**
     * Build a snapshot payload array from a User model.
     * Loads division and office relations if not already loaded.
     *
     * Returns safe empty strings (not null) so snapshot columns
     * are never accidentally null when the data genuinely exists.
     */
    public function resolveUser(User $user): array
    {
        if (! $user->relationLoaded('division')) {
            $user->load('division');
        }
        if (! $user->relationLoaded('office')) {
            $user->load('office');
        }

        return [
            'user_id'           => $user->id,
            'name_snapshot'     => $user->name ?? '',
            'position_snapshot' => $user->position ?? '',
            'division_snapshot' => $user->division?->division_name ?? '',
            'office_snapshot'   => $user->office?->name ?? '',
        ];
    }

    // ── Approval Snapshot ─────────────────────────────────────────────────────

    /**
     * Record a single approval action as an immutable snapshot.
     *
     * Idempotent: if a snapshot already exists for (approvable, step),
     * the existing record is returned without writing a duplicate.
     *
     * @param  Model       $approvable  The parent record (LeaveApplication, etc.)
     * @param  string      $step        Step constant from ApprovalStep
     * @param  int         $sequence    Display order (1, 2, 3…)
     * @param  string      $action      certified / forwarded / approved / rejected
     * @param  User        $approver    The user performing the action
     * @param  string      $remarks     Optional remarks
     * @param  Carbon|null $signedAt    Defaults to now()
     * @return ApprovalSnapshot
     */
    public function recordApproval(
        Model   $approvable,
        string  $step,
        int     $sequence,
        string  $action,
        User    $approver,
        string  $remarks  = '',
        ?Carbon $signedAt = null,
    ): ApprovalSnapshot {

        // ── Idempotency guard ─────────────────────────────────────────────────
        $existing = ApprovalSnapshot::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->getKey())
            ->where('step', $step)
            ->first();

        if ($existing) {
            return $existing;
        }

        // ── Resolve approver data ─────────────────────────────────────────────
        $userData = $this->resolveUser($approver);

        return ApprovalSnapshot::create([
            'approvable_type'   => get_class($approvable),
            'approvable_id'     => $approvable->getKey(),
            'step'              => $step,
            'sequence'          => $sequence,
            'action'            => $action,
            'status'            => in_array($action, ['approved', 'certified', 'forwarded', 'filed', 'prepared', 'reviewed'])
                                       ? 'approved'
                                       : 'rejected',
            'remarks'           => $remarks,
            'signed_at'         => $signedAt ?? now(),
            ...$userData,
        ]);
    }

    // ── Signatory Snapshot ────────────────────────────────────────────────────

    /**
     * Capture signatories for a printable document at the time of generation.
     *
     * Idempotent per role_label: if a snapshot already exists for a given
     * role on this document, it is skipped (the first capture is preserved).
     *
     * @param  Model  $signable      The parent record (LeaveApplication, etc.)
     * @param  array  $signatories   Array of ['role_label' => string, 'user' => User|null]
     * @return Collection<SignatorySnapshot>
     */
    public function captureSignatories(Model $signable, array $signatories): Collection
    {
        $results = collect();

        foreach ($signatories as $entry) {
            $roleLabel = $entry['role_label'];
            $user      = $entry['user'] ?? null;

            // Skip if already captured for this role
            $existing = SignatorySnapshot::where('signable_type', get_class($signable))
                ->where('signable_id', $signable->getKey())
                ->where('role_label', $roleLabel)
                ->first();

            if ($existing) {
                $results->push($existing);
                continue;
            }

            // If no user is provided for this role, store a placeholder
            if (! $user) {
                $results->push(SignatorySnapshot::create([
                    'signable_type'     => get_class($signable),
                    'signable_id'       => $signable->getKey(),
                    'role_label'        => $roleLabel,
                    'user_id'           => null,
                    'name_snapshot'     => '',
                    'position_snapshot' => '',
                    'division_snapshot' => '',
                    'office_snapshot'   => '',
                    'signature_path'    => null,
                    'captured_at'       => now(),
                ]));
                continue;
            }

            $userData = $this->resolveUser($user);

            $results->push(SignatorySnapshot::create([
                'signable_type'  => get_class($signable),
                'signable_id'    => $signable->getKey(),
                'role_label'     => $roleLabel,
                'signature_path' => $user->electronic_signature ?? null,
                'captured_at'    => now(),
                ...$userData,
            ]));
        }

        return $results;
    }

    // ── Convenience: resolve signatory array for Inertia props ────────────────

    /**
     * Return a snapshot-or-live resolved array for a signatory role.
     * Used by controllers to build Inertia props for print forms.
     *
     * @param  Model       $signable
     * @param  string      $roleLabel
     * @param  User|null   $fallbackUser  Live user to fall back to if no snapshot
     * @return array|null
     */
    public function resolveSignatory(
        Model  $signable,
        string $roleLabel,
        ?User  $fallbackUser = null,
    ): ?array {

        $snap = SignatorySnapshot::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->getKey())
            ->where('role_label', $roleLabel)
            ->first();

        if ($snap) {
            return $snap->toSignatoryArray();
        }

        if (! $fallbackUser) {
            return null;
        }

        // Live fallback — not yet snapshotted
        $userData = $this->resolveUser($fallbackUser);
        return [
            'name'           => $userData['name_snapshot'],
            'position'       => $userData['position_snapshot'],
            'division'       => $userData['division_snapshot'],
            'office'         => $userData['office_snapshot'],
            'signature_path' => $fallbackUser->electronic_signature ?? null,
            'captured_at'    => null,
        ];
    }
}
