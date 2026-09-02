<?php

namespace App\Services\PerformanceManagementV2;

use App\Models\IPCRWeightDistribution;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PM V2's own status machine + immutability + weight-validation authority.
 * Deliberately separate from v1's IPCRWorkflowService (which operates on
 * EmployeeIPCR, not EmployeeIpcrV2) — but delegates supervisor-chain
 * resolution and the adjectival rating bands to it directly, since both are
 * pure functions of User/float with no EmployeeIPCR coupling.
 */
class IpcrWorkflowServiceV2
{
    public const STATUS_NEW_TARGET       = 'New Target';
    public const STATUS_TARGETS_APPROVED = 'Targets Approved';
    public const STATUS_FOR_RATING       = 'Submitted for Rating';
    public const STATUS_RATED            = 'Rated';

    public const TRANSITIONS = [
        self::STATUS_NEW_TARGET       => [self::STATUS_TARGETS_APPROVED],
        self::STATUS_TARGETS_APPROVED => [self::STATUS_FOR_RATING],
        self::STATUS_FOR_RATING       => [self::STATUS_RATED],
        self::STATUS_RATED            => [],
    ];

    private const DEFAULT_WEIGHTS = ['strategic' => 30.0, 'core' => 50.0, 'support' => 20.0];

    public function __construct(private IPCRWorkflowService $v1Workflow)
    {
    }

    public function assertMutable(EmployeeIpcrV2 $ipcr): void
    {
        abort_if($ipcr->isFinalized(), 403, 'This IPCR has been rated and is final. It can no longer be modified.');
        abort_if($ipcr->isPeriodClosed(), 403, 'The rating period for this IPCR is closed. Records in a closed period are read-only.');
    }

    public function assertOwner(User $user, EmployeeIpcrV2 $ipcr): void
    {
        abort_if($ipcr->user_id !== $user->id, 403, 'You can only modify your own IPCR.');
    }

    public function canManage(User $user, EmployeeIpcrV2 $ipcr): bool
    {
        $ipcr->loadMissing('user');
        if (! $ipcr->user) {
            return false;
        }

        $supervisor = $this->v1Workflow->immediateSupervisorFor($ipcr->user);

        return $supervisor && $supervisor->id === $user->id;
    }

    public function assertCanManage(User $user, EmployeeIpcrV2 $ipcr): void
    {
        abort_unless($this->canManage($user, $ipcr), 403, "You are not this employee's immediate supervisor and cannot act on this IPCR.");
    }

    public function transition(EmployeeIpcrV2 $ipcr, string $to, array $extra = [], ?string $auditAction = null): EmployeeIpcrV2
    {
        $this->assertMutable($ipcr);

        return DB::transaction(function () use ($ipcr, $to, $extra, $auditAction) {
            $fresh = EmployeeIpcrV2::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$fresh->status] ?? [];
            abort_unless(in_array($to, $allowed, true), 403, "Invalid PM V2 IPCR status change: \"{$fresh->status}\" cannot move to \"{$to}\".");

            $fresh->update(array_merge($extra, ['status' => $to]));

            AuditLogger::log([
                'action'         => $auditAction ?? 'pm_v2_ipcr_status_changed',
                'auditable_type' => EmployeeIpcrV2::class,
                'auditable_id'   => $fresh->id,
                'new_values'     => array_merge(['status' => $to], $extra),
            ]);

            $ipcr->refresh();

            return $ipcr;
        });
    }

    /**
     * Strategic/Core/Support target percentages for a division, from
     * ipcr_weight_distributions when a row exists, else the spec's default
     * 30/50/20 split.
     *
     * @return array{strategic: float, core: float, support: float}
     */
    public function weightTargets(?int $divisionId): array
    {
        $row = $divisionId ? IPCRWeightDistribution::where('division_id', $divisionId)->first() : null;

        if (! $row) {
            return self::DEFAULT_WEIGHTS;
        }

        return [
            'strategic' => (float) $row->strategic,
            'core'      => (float) $row->core,
            'support'   => (float) $row->support,
        ];
    }

    /** @return array{strategic: float, core: float, support: float} */
    public function weightSums(EmployeeIpcrV2 $ipcr): array
    {
        $sums = ['strategic' => 0.0, 'core' => 0.0, 'support' => 0.0];
        foreach ($ipcr->rows as $row) {
            $sums[$row->function_type] += (float) ($row->weight_percent ?? 0);
        }

        return $sums;
    }

    public function assertWeightsValid(EmployeeIpcrV2 $ipcr): void
    {
        $ipcr->loadMissing(['user', 'rows']);
        $targets = $this->weightTargets($ipcr->user?->division_id);
        $sums    = $this->weightSums($ipcr);

        $errors = [];
        foreach ($targets as $group => $target) {
            if (abs($sums[$group] - $target) > 0.01) {
                $errors["weights.{$group}"] = ucfirst($group)." Function rows sum to {$sums[$group]}%, but must sum to {$target}%.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function computeWeightedAverage(EmployeeIpcrV2 $ipcr): ?float
    {
        $ipcr->loadMissing('rows');

        $total  = 0.0;
        $hasAny = false;
        foreach ($ipcr->rows as $row) {
            if ($row->sup_average === null || $row->weight_percent === null) {
                continue;
            }
            $total  += (float) $row->sup_average * ((float) $row->weight_percent / 100);
            $hasAny  = true;
        }

        return $hasAny ? round($total, 2) : null;
    }

    public function finalize(EmployeeIpcrV2 $ipcr): EmployeeIpcrV2
    {
        $finalNumeric = $this->computeWeightedAverage($ipcr);

        return $this->transition($ipcr, self::STATUS_RATED, [
            'submitted_rating_at'     => now(),
            'final_numeric_rating'    => $finalNumeric,
            'final_adjectival_rating' => $this->v1Workflow->adjectivalRating($finalNumeric),
        ], 'pm_v2_ipcr_rated');
    }
}
