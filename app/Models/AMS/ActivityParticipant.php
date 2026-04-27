<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipant extends Model
{
    protected $table = 'ams_activity_participants';

    protected $fillable = [
        'activity_id',
        'participant_id',
        'participant_type',
        'attended',
        'hours_attended',
        'certificate_path',
    ];

    protected $casts = [
        'hours_attended' => 'decimal:2',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
