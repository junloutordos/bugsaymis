<?php

namespace App\Services\SPMS;

use App\Models\SPMS\Dpcr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class DPCRWorkflowService
{
    private const TRANSITIONS = [
        Dpcr::STATUS_DRAFT => [Dpcr::STATUS_SUBMITTED_TO_REVIEWER],
        Dpcr::STATUS_SUBMITTED_TO_REVIEWER => [Dpcr::STATUS_REVIEWED, Dpcr::STATUS_RETURNED],
        Dpcr::STATUS_REVIEWED => [Dpcr::STATUS_SUBMITTED_TO_APPROVER, Dpcr::STATUS_RETURNED],
        Dpcr::STATUS_SUBMITTED_TO_APPROVER => [Dpcr::STATUS_APPROVED, Dpcr::STATUS_RETURNED],
        Dpcr::STATUS_APPROVED => [],
        Dpcr::STATUS_RETURNED => [Dpcr::STATUS_DRAFT],
    ];

    public function __construct(private readonly SPMSRollupService $rollup) {}

    public function submitToReviewer(Dpcr $dpcr, User $actor): Dpcr
    {
        $this->assertCanManage($dpcr, $actor);

        return $this->transition($dpcr, Dpcr::STATUS_SUBMITTED_TO_REVIEWER, ['submitted_to_reviewer_at' => now()]);
    }

    public function review(Dpcr $dpcr, User $actor): Dpcr
    {
        $this->assertCanReview($dpcr, $actor);

        $rolledUp = $this->rollup->rollupIpcrsToDpcr($dpcr);

        return $this->transition($dpcr, Dpcr::STATUS_REVIEWED, [
            'rolled_up_rating' => $rolledUp,
            'reviewed_at' => now(),
        ]);
    }

    public function submitToApprover(Dpcr $dpcr, User $actor): Dpcr
    {
        $this->assertCanReview($dpcr, $actor);

        return $this->transition($dpcr, Dpcr::STATUS_SUBMITTED_TO_APPROVER, ['submitted_to_approver_at' => now()]);
    }

    public function approve(Dpcr $dpcr, User $actor): Dpcr
    {
        $this->assertCanApprove($dpcr, $actor);

        $rating = $dpcr->override_rating !== null
            ? (float) $dpcr->override_rating
            : (float) ($dpcr->rolled_up_rating ?? 0.0);

        return $this->transition($dpcr, Dpcr::STATUS_APPROVED, [
            'final_rating' => $rating,
            'final_adjectival' => $this->adjectivalRating($rating),
            'approved_at' => now(),
        ]);
    }

    public function setOverride(Dpcr $dpcr, User $actor, float $rating, string $reason): Dpcr
    {
        $this->assertCanManageOrReview($dpcr, $actor);

        if ($dpcr->status === Dpcr::STATUS_APPROVED) {
            throw new \InvalidArgumentException('Cannot override a terminal DPCR.');
        }

        $dpcr->update(['override_rating' => $rating, 'override_reason' => $reason]);

        return $dpcr->fresh();
    }

    public function returnToSender(Dpcr $dpcr, User $actor, string $reason): Dpcr
    {
        $this->assertCanReviewOrApprove($dpcr, $actor);

        return $this->transition($dpcr, Dpcr::STATUS_RETURNED, ['return_reason' => $reason]);
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

    private function assertCanManage(Dpcr $dpcr, User $actor): void
    {
        if ($dpcr->ratee_user_id !== $actor->id && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('Only the DPCR ratee (Division Chief) may perform this action.');
        }
    }

    private function assertCanReview(Dpcr $dpcr, User $actor): void
    {
        if (!$actor->hasPermission('spms.dpcr.review') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('You do not have permission to review this DPCR.');
        }
    }

    private function assertCanApprove(Dpcr $dpcr, User $actor): void
    {
        if (!$actor->hasPermission('spms.dpcr.approve') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('You do not have permission to approve this DPCR.');
        }
    }

    private function assertCanManageOrReview(Dpcr $dpcr, User $actor): void
    {
        $isRatee = $dpcr->ratee_user_id === $actor->id;
        if (!$isRatee && !$actor->hasPermission('spms.dpcr.review') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('Only the DPCR ratee or reviewer may set an override rating.');
        }
    }

    private function assertCanReviewOrApprove(Dpcr $dpcr, User $actor): void
    {
        if (!$actor->hasPermission('spms.dpcr.review') && !$actor->hasPermission('spms.dpcr.approve') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('You do not have permission to return this DPCR.');
        }
    }

    private function transition(Dpcr $dpcr, string $to, array $extra = []): Dpcr
    {
        return DB::transaction(function () use ($dpcr, $to, $extra) {
            $locked = Dpcr::whereKey($dpcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$locked->status] ?? [];
            if (!in_array($to, $allowed, true)) {
                throw new \InvalidArgumentException("Cannot transition SPMS DPCR #{$locked->id} from '{$locked->status}' to '{$to}'.");
            }

            $locked->update(array_merge(['status' => $to], $extra));

            return $locked->fresh();
        });
    }
}
