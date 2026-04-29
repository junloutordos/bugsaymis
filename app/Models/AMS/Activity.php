<?php

namespace App\Models\AMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $table = 'ams_activities';

    protected $fillable = [
        'user_id',
        'title',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'total_hours',
        'venue',
        'resource_person',
        'banner',
        'special_order',
        'activity_report',
        'official_documentation',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coProponents(): HasMany
    {
        return $this->hasMany(ActivityCoProponent::class, 'activity_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class, 'activity_id');
    }

    public function studentAttendance(): HasMany
    {
        return $this->hasMany(ActivityStudentAttendance::class, 'activity_id');
    }
}
