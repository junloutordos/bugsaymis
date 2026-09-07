<?php

namespace App\Services\IPCRV2;

use App\Models\Division;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Status-machine authority for IpcrV2Record — literally mirrors
 * IPCRWorkflowService's constants/transitions/immutability rules, per the
 * spec's "reuse v1's mechanism" mandate. Supervisor-chain resolution
 * (immediateSupervisorFor / division-chief lookup) is NOT re-implemented
 * here — it's delegated to the existing IPCRWorkflowService, which already
 * operates on User and has no EmployeeIPCR-specific coupling.
 */
class IpcrV2WorkflowService
{
    public const STATUS_NEW_TARGET       = 'New Target';
    public const STATUS_FOR_REVIEW       = 'For Review';
    public const STATUS_RETURNED         = 'Returned for Revision';
    public const STATUS_TARGETS_APPROVED = 'Targets Approved';
    public const STATUS_FOR_RATING       = 'Submitted for Rating';
    public const STATUS_RATED            = 'Rated & For PMT Review';
    public const STATUS_SUBMITTED_HR     = 'Submitted to HR';
    public const STATUS_SUBMITTED_PMT    = 'Submitted to PMT';
    public const STATUS_PMT_RETURNED     = 'PMT Returned for Revision';
    public const STATUS_PMT_APPROVED     = 'Approved by PMT';
    public const STATUS_DIRECTOR_SIGNED  = 'Director Signed';

    public const TRANSITIONS = [
        self::STATUS_NEW_TARGET       => [self::STATUS_FOR_REVIEW],
        self::STATUS_FOR_REVIEW       => [self::STATUS_TARGETS_APPROVED, self::STATUS_RETURNED],
        self::STATUS_RETURNED         => [self::STATUS_FOR_REVIEW],
        self::STATUS_TARGETS_APPROVED => [self::STATUS_FOR_RATING],
        self::STATUS_FOR_RATING       => [self::STATUS_RATED, self::STATUS_TARGETS_APPROVED],
        self::STATUS_RATED            => [self::STATUS_SUBMITTED_HR, self::STATUS_SUBMITTED_PMT, self::STATUS_TARGETS_APPROVED],
        self::STATUS_SUBMITTED_HR     => [self::STATUS_SUBMITTED_PMT],
        self::STATUS_SUBMITTED_PMT    => [self::STATUS_PMT_APPROVED, self::STATUS_PMT_RETURNED],
        self::STATUS_PMT_RETURNED     => [self::STATUS_TARGETS_APPROVED],
        self::STATUS_PMT_APPROVED     => [self::STATUS_DIRECTOR_SIGNED],
        self::STATUS_DIRECTOR_SIGNED  => [],
    ];

    public function __construct(
        private ?IPCRWorkflowService $chain = null,
        private ?IpcrV2RatingService $rating = null
    ) {
        $this->chain ??= app(IPCRWorkflowService::class);
        $this->rating ??= app(IpcrV2RatingService::class);
    }

    public function assertMutable(IpcrV2Record $ipcr): void
    {
        abort_if($ipcr->isFinalized(), 403, 'This IPCR V2 has been signed by the Director and is final.');
        abort_if($ipcr->isPeriodClosed(), 403, 'The rating period for this IPCR V2 is closed.');
    }

    public function assertOwner(User $user, IpcrV2Record $ipcr): void
    {
        abort_if($ipcr->user_id !== $user->id, 403, 'You can only modify your own IPCR V2.');
    }

    public function canManage(User $user, IpcrV2Record $ipcr): bool
    {
        $ipcr->loadMissing('user');
        if (! $ipcr->user) {
            return $user->hasRole('OCD');
        }

        $supervisor = $this->chain->immediateSupervisorFor($ipcr->user);

        return $user->hasRole('OCD') || ($supervisor && $supervisor->id === $user->id);
    }

    public function assertCanManage(User $user, IpcrV2Record $ipcr): void
    {
        abort_unless($this->canManage($user, $ipcr), 403, "You are not this employee's immediate supervisor and cannot act on this IPCR V2.");
    }

    public function canEndorse(User $user, IpcrV2Record $ipcr): bool
    {
        $ipcr->loadMissing('user');
        $divisionChiefId = Division::where('id', $ipcr->user?->division_id)->value('division_chief_id');

        return $user->hasRole('OCD') || ($divisionChiefId && $user->id == $divisionChiefId);
    }

    public function assertCanEndorse(User $user, IpcrV2Record $ipcr): void
    {
        abort_unless($this->canEndorse($user, $ipcr), 403, "You are not this employee's Division Chief and cannot endorse this IPCR V2.");
    }

    public function transition(IpcrV2Record $ipcr, string $to, array $extra = [], ?string $auditAction = null): IpcrV2Record
    {
        $this->assertMutable($ipcr);

        return DB::transaction(function () use ($ipcr, $to, $extra, $auditAction) {
            $fresh = IpcrV2Record::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$fresh->status] ?? [];
            abort_unless(in_array($to, $allowed, true), 403, "Invalid IPCR V2 status change: \"{$fresh->status}\" cannot move to \"{$to}\".");

            $fresh->update(array_merge($extra, ['status' => $to]));

            AuditLogger::log([
                'action' => $auditAction ?? 'ipcr_v2_status_changed',
                'auditable_type' => IpcrV2Record::class,
                'auditable_id' => $fresh->id,
                'new_values' => array_merge(['status' => $to], $extra),
            ]);

            $ipcr->refresh();

            return $ipcr;
        });
    }

    public function assertPeriodAcceptsNewTargets(IPCRRatingPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'rating_period_id' => "The rating period \"{$period->label}\" is closed and no longer accepts IPCR V2 records.",
            ]);
        }
    }

    public function assertNoDuplicateForPeriod(int $userId, int $periodId, ?int $ignoreId = null): void
    {
        $exists = IpcrV2Record::where('user_id', $userId)
            ->where('rating_period_id', $periodId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'rating_period_id' => 'This employee already has an IPCR V2 for this rating period.',
            ]);
        }
    }

    public function finalize(IpcrV2Record $ipcr, User $director): IpcrV2Record
    {
        $finalNumeric = $this->rating->computeFinalRating($ipcr);

        return $this->transition($ipcr, self::STATUS_DIRECTOR_SIGNED, [
            'director_signed_at' => now(),
            'director_signature' => $director->electronic_signature,
            'final_numeric_rating' => $finalNumeric,
            'final_adjectival_rating' => $this->rating->adjectivalRating($finalNumeric),
        ], 'ipcr_v2_director_signed');
    }
}
