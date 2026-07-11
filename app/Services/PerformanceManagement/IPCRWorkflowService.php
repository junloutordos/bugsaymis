<?php

namespace App\Services\PerformanceManagement;

use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central authority for the IPCR workflow (CSC MC No. 6, s. 2012 — SPMS).
 *
 * Enforces four layers of integrity:
 *  1. Terminal immutability — a Director Signed IPCR can never change.
 *  2. Period immutability   — everything in a closed rating period is read-only.
 *  3. Actor authorization   — owner / division chief / plan rater checks.
 *  4. Status state machine  — every status write goes through transition().
 */
class IPCRWorkflowService
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

    private const FUNCTION_WEIGHTS = [
        'Strategic Functions' => 0.30,
        'Core Functions'      => 0.55,
        'Support Functions'   => 0.15,
    ];

    /* ----------------------------------------------------------------
     | Layers 1 + 2 — immutability
     * ---------------------------------------------------------------- */

    public function assertMutable(EmployeeIPCR $ipcr): void
    {
        abort_if(
            $ipcr->isFinalized(),
            403,
            'This IPCR has been signed by the Director and is final. It can no longer be modified.'
        );

        abort_if(
            $ipcr->isPeriodClosed(),
            403,
            'The rating period for this IPCR is closed. Records in a closed period are read-only.'
        );
    }

    /* ----------------------------------------------------------------
     | Layer 3 — actor authorization
     * ---------------------------------------------------------------- */

    public function assertOwner(User $user, EmployeeIPCR $ipcr): void
    {
        abort_if($ipcr->user_id !== $user->id, 403, 'You can only modify your own IPCR.');
    }

    public function canManage(User $user, EmployeeIPCR $ipcr): bool
    {
        $employeeDivisionChiefId = Division::where('id', $ipcr->user?->division_id)->value('division_chief_id');

        return $user->hasRole('OCD') || ($employeeDivisionChiefId && $user->id == $employeeDivisionChiefId);
    }

    public function assertCanManage(User $user, EmployeeIPCR $ipcr): void
    {
        $ipcr->loadMissing('user');

        abort_unless(
            $this->canManage($user, $ipcr),
            403,
            "You are not this employee's Division Chief and cannot act on this IPCR."
        );
    }

    public function canRatePlan(User $user, EmployeeIPCR $ipcr, WorkDistributionPlan $plan): bool
    {
        $plan->loadMissing(['offices', 'committees', 'specialAssignments']);
        $ipcr->loadMissing('user');

        $divisionId = Division::where('division_chief_id', $user->id)->value('id') ?? $user->division_id;
        $isDCForEmployee = $divisionId && $ipcr->user->division_id == $divisionId;

        return $user->hasRole('OCD') || $isDCForEmployee || match ($plan->rated_by) {
            'Unit Head'      => $plan->offices->contains(fn ($o) => $o->unit_head == $user->id),
            'Committee Head' => $plan->committees->contains(fn ($c) => $c->head_id == $user->id),
            'Coordinator'    => $plan->specialAssignments->contains(fn ($a) => $a->coordinator_id == $user->id),
            default          => false,
        };
    }

    /* ----------------------------------------------------------------
     | Layer 4 — state machine
     * ---------------------------------------------------------------- */

    /**
     * Move an IPCR to a new status. Validates immutability and the
     * transition map, applies $extra attributes atomically, logs.
     */
    public function transition(EmployeeIPCR $ipcr, string $to, array $extra = [], ?string $auditAction = null): EmployeeIPCR
    {
        $this->assertMutable($ipcr);

        return DB::transaction(function () use ($ipcr, $to, $extra, $auditAction) {
            $fresh = EmployeeIPCR::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$fresh->status] ?? [];
            abort_unless(
                in_array($to, $allowed, true),
                403,
                "Invalid IPCR status change: \"{$fresh->status}\" cannot move to \"{$to}\"."
            );

            $fresh->update(array_merge($extra, ['status' => $to]));

            AuditLogger::log([
                'action'         => $auditAction ?? 'ipcr_status_changed',
                'auditable_type' => EmployeeIPCR::class,
                'auditable_id'   => $fresh->id,
                'new_values'     => array_merge(['status' => $to], $extra),
            ]);

            $ipcr->refresh();

            return $ipcr;
        });
    }

    /* ----------------------------------------------------------------
     | Creation guards
     * ---------------------------------------------------------------- */

    public function assertPeriodAcceptsNewTargets(IPCRRatingPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'rating_period_id' => "The rating period \"{$period->label}\" is closed and no longer accepts IPCRs.",
            ]);
        }
    }

    public function assertNoDuplicateForPeriod(int $userId, int $periodId, ?int $ignoreIpcrId = null): void
    {
        $exists = EmployeeIPCR::where('user_id', $userId)
            ->where('rating_period_id', $periodId)
            ->when($ignoreIpcrId, fn ($q) => $q->where('id', '!=', $ignoreIpcrId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'rating_period_id' => 'You already have an IPCR for this rating period. The SPMS allows one IPCR per employee per semestral period.',
            ]);
        }
    }

    /* ----------------------------------------------------------------
     | Ratings (CSC SPMS: 5-point scale, strategic/core/support weights)
     * ---------------------------------------------------------------- */

    public function normalizeFunctionType(?string $raw): string
    {
        if (! $raw) return 'Uncategorized';
        $t = strtolower(trim($raw));
        if (in_array($t, ['strategic', 'strategic functions', 'strategic function'])) return 'Strategic Functions';
        if (in_array($t, ['core', 'core functions', 'core function']))               return 'Core Functions';
        if (in_array($t, ['support', 'support functions', 'support function']))      return 'Support Functions';

        return trim($raw);
    }

    /**
     * Weighted final rating (Strategic 30%, Core 55%, Support 15%).
     * Mirrors DivisionChiefIPCRShow.vue / PMTIPCRShow.vue.
     */
    public function computeWeightedAverage($plans): ?float
    {
        $groups = [];

        foreach ($plans as $plan) {
            $ft     = $this->normalizeFunctionType($plan->performance_indicator?->agencyOutcome?->function_type);
            $supAvg = $plan->pivot?->sup_average;
            if ($supAvg === null || $supAvg === '') continue;
            $groups[$ft]['total'] = ($groups[$ft]['total'] ?? 0) + (float) $supAvg;
            $groups[$ft]['count'] = ($groups[$ft]['count'] ?? 0) + 1;
        }

        $totalWeighted = 0;
        $hasAny        = false;
        foreach ($groups as $ft => $data) {
            $weight         = self::FUNCTION_WEIGHTS[$ft] ?? 0;
            $totalWeighted += ($data['total'] / $data['count']) * $weight;
            $hasAny         = true;
        }

        return $hasAny ? round($totalWeighted, 2) : null;
    }

    /**
     * CSC SPMS adjectival bands on the 5-point scale.
     * Single server-side switch point for the rating scale.
     */
    public function adjectivalRating(?float $value): ?string
    {
        if ($value === null) return null;

        return match (true) {
            $value >= 4.51 => 'Outstanding',
            $value >= 3.51 => 'Very Satisfactory',
            $value >= 2.51 => 'Satisfactory',
            $value >= 1.51 => 'Unsatisfactory',
            default        => 'Poor',
        };
    }

    /* ----------------------------------------------------------------
     | Finalization
     * ---------------------------------------------------------------- */

    /**
     * Director signs the IPCR: terminal transition + immutable snapshot
     * of the final numeric and adjectival rating.
     */
    public function finalize(EmployeeIPCR $ipcr, User $director): EmployeeIPCR
    {
        $ipcr->loadMissing('plans.performance_indicator.agencyOutcome');
        $finalNumeric = $this->computeWeightedAverage($ipcr->plans);

        return $this->transition($ipcr, self::STATUS_DIRECTOR_SIGNED, [
            'director_signed_at'      => now(),
            'director_signature'      => $director->electronic_signature,
            'final_numeric_rating'    => $finalNumeric,
            'final_adjectival_rating' => $this->adjectivalRating($finalNumeric),
        ], 'ipcr_director_signed');
    }
}
