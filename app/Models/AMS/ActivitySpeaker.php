<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivitySpeaker extends Model
{
    protected $table = 'ams_activity_speakers';

    protected $fillable = [
        'activity_id',
        'name',
        'designation',
        'topic',
        'sort_order',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ActivitySpeakerEvaluation::class, 'speaker_id');
    }
}
