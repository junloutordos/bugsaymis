<?php

namespace App\Models\HomeroomAttendance;

use App\Models\FacultyLoading\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityLog extends Model
{
    protected $table = 'homeroom_activity_logs';

    protected $fillable = [
        'section_id',
        'title',
        'activity_date',
        'is_off_campus',
        'created_by',
    ];

    protected $casts = [
        'section_id'    => 'integer',
        'activity_date' => 'date:Y-m-d',
        'is_off_campus' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ActivityRecord::class, 'homeroom_activity_log_id');
    }
}
