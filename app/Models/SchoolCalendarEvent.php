<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolCalendarEvent extends Model
{
    protected $table = 'school_calendar_events';

    protected $fillable = [
        'date',
        'event_type',
        'grade_level',
        'title',
        'created_by',
    ];

    protected $casts = [
        'date'        => 'date:Y-m-d',
        'grade_level' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
