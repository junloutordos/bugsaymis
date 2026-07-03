<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySpeakerEvaluation extends Model
{
    protected $table = 'ams_activity_speaker_evaluations';

    protected $fillable = [
        'activity_id',
        'speaker_id',
        'participant_type',
        'participant_id',
        'evaluator_name',
        // Part I — Topic/Subject Matter
        'topic_depth_of_content', 'topic_scope_coverage', 'topic_relevance_appropriateness',
        // Part II — Resource Person
        'attainment_of_objectives',
        'mastery_1_command_of_subject', 'mastery_2_pace_timing',
        'mastery_3_theory_application_balance', 'mastery_4_current_trends',
        'presentation_1_listened', 'presentation_2_answered_questions',
        'presentation_3_inspired_participation', 'presentation_4_held_interest',
        'acceptability_as_speaker',
        // Part III — open-ended
        'effectiveness_reason',
        'improvement_suggestions',
        'other_topics_wanted',
    ];

    public const TOPIC_WEIGHTS = [
        'excellent'     => 3,
        'satisfactory'  => 2,
        'low'           => 1,
    ];

    public const SPEAKER_WEIGHTS = [
        'strongly_agree'    => 5,
        'agree'             => 4,
        'neutral'           => 3,
        'disagree'          => 2,
        'strongly_disagree' => 1,
        'not_applicable'    => null,
    ];

    public const PART_I_FIELDS = ['topic_depth_of_content', 'topic_scope_coverage', 'topic_relevance_appropriateness'];

    public const PART_II_FIELDS = [
        'attainment_of_objectives',
        'mastery_1_command_of_subject', 'mastery_2_pace_timing',
        'mastery_3_theory_application_balance', 'mastery_4_current_trends',
        'presentation_1_listened', 'presentation_2_answered_questions',
        'presentation_3_inspired_participation', 'presentation_4_held_interest',
        'acceptability_as_speaker',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(ActivitySpeaker::class, 'speaker_id');
    }

    public function partIAverage(): ?float
    {
        $scores = collect(self::PART_I_FIELDS)
            ->map(fn ($f) => self::TOPIC_WEIGHTS[$this->$f] ?? null)
            ->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }

    public function partIIAverage(): ?float
    {
        $scores = collect(self::PART_II_FIELDS)
            ->map(fn ($f) => self::SPEAKER_WEIGHTS[$this->$f] ?? null)
            ->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }
}
