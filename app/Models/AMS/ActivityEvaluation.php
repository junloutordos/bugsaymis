<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEvaluation extends Model
{
    protected $table = 'ams_activity_evaluations';

    protected $fillable = [
        'activity_id',
        'participant_type',
        'participant_id',
        'evaluator_name',
        'sex',
        // Section A
        'obj_1', 'obj_2', 'obj_3', 'obj_4',
        // Section B
        'mgmt_1', 'mgmt_2', 'mgmt_3', 'mgmt_4', 'mgmt_5', 'mgmt_6',
        // Section C
        'phys_1', 'phys_2', 'phys_3',
        // Open-ended
        'suggestions',
        'other_comments',
    ];

    // Likert scale numeric weights for averaging
    public const WEIGHTS = [
        'strongly_agree'    => 5,
        'agree'             => 4,
        'neutral'           => 3,
        'disagree'          => 2,
        'strongly_disagree' => 1,
        'not_applicable'    => null,
    ];

    public const SECTION_A_FIELDS = ['obj_1', 'obj_2', 'obj_3', 'obj_4'];
    public const SECTION_B_FIELDS = ['mgmt_1', 'mgmt_2', 'mgmt_3', 'mgmt_4', 'mgmt_5', 'mgmt_6'];
    public const SECTION_C_FIELDS = ['phys_1', 'phys_2', 'phys_3'];

    public const ALL_RATING_FIELDS = [
        ...self::SECTION_A_FIELDS,
        ...self::SECTION_B_FIELDS,
        ...self::SECTION_C_FIELDS,
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Average weighted score for a given set of field names, ignoring not_applicable.
     */
    public function sectionAverage(array $fields): ?float
    {
        $scores = collect($fields)
            ->map(fn($f) => self::WEIGHTS[$this->$f] ?? null)
            ->filter(fn($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }
}
