<?php

namespace App\Services\SPMS;

use App\Models\SPMS\Opcr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class OPCRWorkflowService
{
    private const TRANSITIONS = [
        Opcr::STATUS_DRAFT => [Opcr::STATUS_SUBMITTED_TO_ED],
        Opcr::STATUS_SUBMITTED_TO_ED => [Opcr::STATUS_ED_APPROVED, Opcr::STATUS_RETURNED],
        Opcr::STATUS_ED_APPROVED => [],
        Opcr::STATUS_RETURNED => [Opcr::STATUS_DRAFT],
    ];

    public function __construct(private readonly SPMSRollupService $rollup) {}

    public function submitToExecutiveDirector(Opcr $opcr, User $actor): Opcr
    {
        $this->assertCanManage($opcr, $actor);

        $rolledUp = $this->rollup->rollupDpcrsToOpcr($opcr);

        return $this->transition($opcr, Opcr::STATUS_SUBMITTED_TO_ED, [
            'rolled_up_rating' => $rolledUp,
            'submitted_to_ed_at' => now(),
        ]);
    }

    public function approve(Opcr $opcr, User $actor): Opcr
    {
        $this->assertCanApprove($opcr, $actor);

        $rating = $opcr->override_rating !== null
            ? (float) $opcr->override_rating
            : (float) ($opcr->rolled_up_rating ?? 0.0);

        return $this->transition($opcr, Opcr::STATUS_ED_APPROVED, [
            'final_rating' => $rating,
            'final_adjectival' => $this->adjectivalRating($rating),
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
    }

    public function setOverride(Opcr $opcr, User $actor, float $rating, string $reason): Opcr
    {
        $this->assertCanManageOrApprove($opcr, $actor);

        if ($opcr->status === Opcr::STATUS_ED_APPROVED) {
            throw new \InvalidArgumentException('Cannot override a terminal OPCR.');
        }

        $opcr->update(['override_rating' => $rating, 'override_reason' => $reason]);

        return $opcr->fresh();
    }

    public function returnToSender(Opcr $opcr, User $actor, string $reason): Opcr
    {
        $this->assertCanApprove($opcr, $actor);

        return $this->transition($opcr, Opcr::STATUS_RETURNED, ['return_reason' => $reason]);
    }

    public function adjectivalRating(float $rating): string
    {
        return match (true) {
            $rating >= 4.51 => 'Outstanding',
            $rating >= 3.51 => 'Very Satisfactory',
            $rating >= 2.51 => 'Satisfactory',
            $rating >= 1.51 => 'Unsatisfactory',
            default => 'Poor',
        };
    }

    private function assertCanManage(Opcr $opcr, User $actor): void
    {
        if ($opcr->ratee_user_id !== $actor->id && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('Only the OPCR ratee (Campus Director) may perform this action.');
        }
    }

    private function assertCanApprove(Opcr $opcr, User $actor): void
    {
        if (!$actor->hasPermission('spms.opcr.approve') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('You do not have permission to approve this OPCR.');
        }
    }

    private function assertCanManageOrApprove(Opcr $opcr, User $actor): void
    {
        $isRatee = $opcr->ratee_user_id === $actor->id;
        if (!$isRatee && !$actor->hasPermission('spms.opcr.approve') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('Only the OPCR ratee or Executive Director may set an override rating.');
        }
    }

    private function transition(Opcr $opcr, string $to, array $extra = []): Opcr
    {
        return DB::transaction(function () use ($opcr, $to, $extra) {
            $locked = Opcr::whereKey($opcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$locked->status] ?? [];
            if (!in_array($to, $allowed, true)) {
                throw new \InvalidArgumentException("Cannot transition SPMS OPCR #{$locked->id} from '{$locked->status}' to '{$to}'.");
            }

            $locked->update(array_merge(['status' => $to], $extra));

            return $locked->fresh();
        });
    }
}
