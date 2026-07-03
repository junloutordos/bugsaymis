<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityTwsEvaluation extends Model
{
    protected $table = 'ams_activity_tws_evaluations';

    protected $fillable = [
        'activity_id',
        'participant_type',
        'participant_id',
        'evaluator_name',
        'position_function',
        // Section A — Content
        'content_1', 'content_2', 'content_3', 'content_4', 'content_5',
        // Section B — Management
        'mgmt_length_of_program', 'mgmt_schedule', 'mgmt_secretariat_support',
        'mgmt_venue', 'mgmt_accommodation', 'mgmt_food_meals',
        // Section C — Overall
        'overall_1_objectives_accomplished', 'overall_2_knowledge_increased',
        // Open-ended
        'suggestions',
        'other_comments',
    ];

    // Section A / C use the standard agree/disagree scale
    public const AGREE_WEIGHTS = [
        'strongly_agree'    => 5,
        'agree'             => 4,
        'neutral'           => 3,
        'disagree'          => 2,
        'strongly_disagree' => 1,
        'not_applicable'    => null,
    ];

    // Section B uses a satisfaction scale
    public const SATISFACTION_WEIGHTS = [
        'very_satisfied'    => 5,
        'satisfied'         => 4,
        'neutral'           => 3,
        'dissatisfied'      => 2,
        'very_dissatisfied' => 1,
        'not_applicable'    => null,
    ];

    public const SECTION_A_FIELDS = ['content_1', 'content_2', 'content_3', 'content_4', 'content_5'];

    public const SECTION_B_FIELDS = [
        'mgmt_length_of_program', 'mgmt_schedule', 'mgmt_secretariat_support',
        'mgmt_venue', 'mgmt_accommodation', 'mgmt_food_meals',
    ];

    public const SECTION_C_FIELDS = ['overall_1_objectives_accomplished', 'overall_2_knowledge_increased'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * Average weighted score for a set of fields using the given weight map, ignoring not_applicable.
     */
    public function sectionAverage(array $fields, array $weights): ?float
    {
        $scores = collect($fields)
            ->map(fn ($f) => $weights[$this->$f] ?? null)
            ->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }
}
