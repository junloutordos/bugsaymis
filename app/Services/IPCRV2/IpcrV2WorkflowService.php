<?php

namespace App\Services\IPCRV2;

use App\Models\Division;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2StatusLog;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DigitalSignatureService;
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
        private ?IpcrV2RatingService $rating = null,
        private ?DigitalSignatureService $signature = null
    ) {
        $this->chain ??= app(IPCRWorkflowService::class);
        $this->rating ??= app(IpcrV2RatingService::class);
        $this->signature ??= app(DigitalSignatureService::class);
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

    public function transition(
        IpcrV2Record $ipcr,
        string $to,
        array $extra = [],
        ?string $auditAction = null,
        ?User $actor = null,
        ?string $remarks = null,
        string $actionType = 'status_changed',
        bool $signedViaPin = false,
    ): IpcrV2Record {
        $this->assertMutable($ipcr);

        $updated = DB::transaction(function () use ($ipcr, $to, $extra, $auditAction, $actor, $remarks, $actionType, $signedViaPin) {
            $fresh = IpcrV2Record::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $fresh->status;

            $allowed = self::TRANSITIONS[$fresh->status] ?? [];
            abort_unless(in_array($to, $allowed, true), 403, "Invalid IPCR V2 status change: \"{$fresh->status}\" cannot move to \"{$to}\".");

            $fresh->update(array_merge($extra, ['status' => $to, 'remarks' => $remarks]));

            AuditLogger::log([
                'action' => $auditAction ?? 'ipcr_v2_status_changed',
                'auditable_type' => IpcrV2Record::class,
                'auditable_id' => $fresh->id,
                'new_values' => array_merge(['status' => $to], $extra),
            ]);

            $this->logAction($fresh, $actor, $actionType, $fromStatus, $to, $remarks, $signedViaPin);

            $ipcr->refresh();

            return $ipcr;
        });

        $this->notifyOnTransition($updated, $to, $remarks);

        return $updated;
    }

    /**
     * Write a timeline row without necessarily changing status — used
     * internally by transition() and directly by callers logging a
     * non-status-changing milestone (e.g. Admin's reopen action).
     */
    public function logAction(
        IpcrV2Record $ipcr,
        ?User $actor,
        string $actionType,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $remarks = null,
        bool $signedViaPin = false,
    ): IpcrV2StatusLog {
        return IpcrV2StatusLog::create([
            'ipcr_v2_record_id' => $ipcr->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action_type' => $actionType,
            'remarks' => $remarks,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->roles->pluck('name')->implode(', ') ?: null,
            'signed_via_pin' => $signedViaPin,
            'signature_snapshot' => $signedViaPin ? $actor?->electronic_signature : null,
        ]);
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

    public function finalize(IpcrV2Record $ipcr, User $director, ?string $pin = null): IpcrV2Record
    {
        $this->signature->assertSigningPin($director, $pin);

        $finalNumeric = $this->rating->computeFinalRating($ipcr);

        return $this->transition($ipcr, self::STATUS_DIRECTOR_SIGNED, [
            'director_signed_at' => now(),
            'director_signature' => $director->electronic_signature,
            'final_numeric_rating' => $finalNumeric,
            'final_adjectival_rating' => $this->rating->adjectivalRating($finalNumeric),
            'locked_at' => now(),
            'locked_by_id' => $director->id,
        ], 'ipcr_v2_director_signed', actor: $director, actionType: 'signed', signedViaPin: ! empty($director->signature_pin));
    }

    /**
     * Resolve recipient(s) for a transition and fire in-app + email
     * notifications. Silent (no recipients) for internal milestones that
     * don't need a separate notice — STATUS_RATED is immediately followed
     * by a STATUS_SUBMITTED_PMT transition in the same request
     * (DivisionChiefIpcrV2Controller::submitToPMT), which does notify.
     */
    private function notifyOnTransition(IpcrV2Record $ipcr, string $to, ?string $remarks): void
    {
        $ipcr->loadMissing('user', 'period');
        $employee = $ipcr->user;
        if (! $employee) {
            return;
        }

        $recipients = match ($to) {
            self::STATUS_FOR_REVIEW, self::STATUS_FOR_RATING => array_filter([$this->chain->immediateSupervisorFor($employee)]),
            self::STATUS_TARGETS_APPROVED, self::STATUS_RETURNED, self::STATUS_PMT_RETURNED,
            self::STATUS_PMT_APPROVED, self::STATUS_DIRECTOR_SIGNED => [$employee],
            self::STATUS_SUBMITTED_PMT => User::havingRole('PMT')->get()->all(),
            default => [],
        };

        foreach (array_filter($recipients) as $recipient) {
            \App\Services\NotificationService::notifyUser(
                $recipient,
                'IPCR V2',
                $ipcr->period?->label ?? "IPCR V2 #{$ipcr->id}",
                $to,
                $this->urlFor($recipient, $ipcr),
                $remarks,
            );

            \Illuminate\Support\Facades\Mail::to($recipient->email)->queue(
                new \App\Mail\IpcrV2StatusMail($ipcr, $recipient, $to, $remarks)
            );
        }
    }

    private function urlFor(User $recipient, IpcrV2Record $ipcr): string
    {
        return match (true) {
            $recipient->hasAnyRole(['OCD', 'PMT']) => route('pmt-ipcr-v2.show', $ipcr->id),
            $recipient->hasRole('DivisionChief') => route('division-chief-ipcr-v2.show', $ipcr->id),
            $recipient->hasRole('HR') => route('hr-ipcr-v2.show', $ipcr->id),
            default => route('employee-ipcr-v2.show', $ipcr->id),
        };
    }
}
