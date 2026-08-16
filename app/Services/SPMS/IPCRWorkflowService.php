<?php

namespace App\Services\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class IPCRWorkflowService
{
    private const TRANSITIONS = [
        Ipcr::STATUS_DRAFT_TARGET => [Ipcr::STATUS_TARGET_SUBMITTED],
        Ipcr::STATUS_TARGET_SUBMITTED => [Ipcr::STATUS_TARGET_APPROVED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_TARGET_APPROVED => [Ipcr::STATUS_SUBMITTED_FOR_RATING],
        Ipcr::STATUS_SUBMITTED_FOR_RATING => [Ipcr::STATUS_RATED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_RATED => [Ipcr::STATUS_DC_REVIEWED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_DC_REVIEWED => [Ipcr::STATUS_PMT_HR_REVIEWED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_PMT_HR_REVIEWED => [Ipcr::STATUS_DIRECTOR_SIGNED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_DIRECTOR_SIGNED => [],
        Ipcr::STATUS_RETURNED => [Ipcr::STATUS_DRAFT_TARGET],
    ];

    public function submitTarget(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanManage($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_TARGET_SUBMITTED, ['target_submitted_at' => now()]);
    }

    public function approveTarget(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_TARGET_APPROVED, ['target_approved_at' => now()]);
    }

    public function submitForRating(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanManage($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_SUBMITTED_FOR_RATING, ['submitted_for_rating_at' => now()]);
    }

    public function rate(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        foreach ($ipcr->targets as $target) {
            if ($target->rating_avg === null && $target->rating_q !== null && $target->rating_e !== null && $target->rating_t !== null) {
                $average = round(((float) $target->rating_q + (float) $target->rating_e + (float) $target->rating_t) / 3, 2);
                $target->update(['rating_avg' => $average]);
            }
        }

        return $this->transition($ipcr, Ipcr::STATUS_RATED, ['rated_at' => now()]);
    }

    public function reviewByDivisionChief(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_DC_REVIEWED);
    }

    public function reviewByPmtHr(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_PMT_HR_REVIEWED);
    }

    public function finalize(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        $rating = $this->computeWeightedAverage($ipcr);

        return $this->transition($ipcr, Ipcr::STATUS_DIRECTOR_SIGNED, [
            'final_rating' => $rating,
            'final_adjectival' => $this->adjectivalRating($rating),
            'director_signed_at' => now(),
        ]);
    }

    public function returnToSender(Ipcr $ipcr, User $actor, string $reason): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_RETURNED, ['return_reason' => $reason]);
    }

    public function computeWeightedAverage(Ipcr $ipcr): float
    {
        $weights = (new WeightProfileResolver())->resolve(
            'ipcr',
            $ipcr->user->division_id ?? null,
            $ipcr->fiscalPeriod->fiscal_year
        );

        $averages = ['strategic' => 0.0, 'core' => 0.0, 'support' => 0.0];

        foreach (['strategic', 'core', 'support'] as $type) {
            $targets = $ipcr->targets->where('function_type', $type)->whereNotNull('rating_avg');
            if ($targets->isNotEmpty()) {
                $averages[$type] = (float) $targets->avg('rating_avg');
            }
        }

        return round(
            $averages['strategic'] * ($weights['strategic_pct'] / 100)
            + $averages['core'] * ($weights['core_pct'] / 100)
            + $averages['support'] * ($weights['support_pct'] / 100),
            2
        );
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

    private function assertCanManage(Ipcr $ipcr, User $actor): void
    {
        if ($ipcr->user_id !== $actor->id && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('Only the IPCR owner may perform this action.');
        }
    }

    private function assertCanReview(Ipcr $ipcr, User $actor): void
    {
        if (!$actor->hasPermission('spms.ipcr.review') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('You do not have permission to review this IPCR.');
        }
    }

    private function transition(Ipcr $ipcr, string $to, array $extra = []): Ipcr
    {
        return DB::transaction(function () use ($ipcr, $to, $extra) {
            $locked = Ipcr::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$locked->status] ?? [];
            if (!in_array($to, $allowed, true)) {
                throw new \InvalidArgumentException("Cannot transition SPMS IPCR #{$locked->id} from '{$locked->status}' to '{$to}'.");
            }

            $locked->update(array_merge(['status' => $to], $extra));

            return $locked->fresh();
        });
    }
}
