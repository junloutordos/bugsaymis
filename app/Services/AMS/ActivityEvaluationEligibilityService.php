<?php

namespace App\Services\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityTwsEvaluation;
use Illuminate\Support\Collection;

class ActivityEvaluationEligibilityService
{
    public function hasEvaluated(Activity $activity, string $participantType, int $participantId): bool
    {
        return $this->queryFor($activity)
            ->where('activity_id', $activity->id)
            ->where('participant_type', $participantType)
            ->where('participant_id', $participantId)
            ->exists();
    }

    /** @return Collection<string, bool> */
    public function evaluatedMap(Activity $activity): Collection
    {
        return $this->queryFor($activity)
            ->where('activity_id', $activity->id)
            ->get(['participant_type', 'participant_id'])
            ->mapWithKeys(fn ($evaluation) => [
                $evaluation->participant_type.':'.$evaluation->participant_id => true,
            ]);
    }

    private function queryFor(Activity $activity)
    {
        return $activity->isTrainingWorkshopSeminar()
            ? ActivityTwsEvaluation::query()
            : ActivityEvaluation::query();
    }
}
