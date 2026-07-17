<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;

class BellScheduleOverride extends Model
{
    protected $table = 'bell_schedule_overrides';

    protected $fillable = [
        'timetable_key',
        'rows',
        'updated_by',
    ];

    protected $casts = [
        'rows' => 'array',
    ];
}
