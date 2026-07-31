<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpActivity;
use App\Models\AMS\Activity as AmsActivity;

class AlpAmsIntegrationService
{
    public function syncApprovedActivity(AlpActivity $activity): AmsActivity
    {
        $activity->loadMissing('cycle.program');

        $ams = $activity->amsActivity ?: new AmsActivity;
        $ams->fill([
            'user_id' => $activity->prepared_by ?: $activity->cycle->adviser_id,
            'title' => '[ALP] '.$activity->cycle->program->name.' - '.$activity->title,
            'activity_type' => AmsActivity::TYPE_IN_HOUSE,
            'start_date' => $activity->start_date,
            'start_time' => $activity->start_time,
            'end_date' => $activity->end_date,
            'end_time' => $activity->end_time,
            'venue' => $activity->venue,
            'what_to_bring' => $activity->resources_needed,
        ]);
        $ams->save();

        if (! $activity->ams_activity_id) {
            $activity->update(['ams_activity_id' => $ams->id]);
        }

        return $ams;
    }
}
