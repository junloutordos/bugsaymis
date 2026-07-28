<?php

namespace App\Models\HomeroomAttendance;

use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlagAttendanceDate extends Model
{
    protected $table = 'homeroom_flag_attendance_dates';

    protected $fillable = [
        'section_id',
        'school_year_id',
        'event_type',
        'date',
        'taken_by',
    ];

    protected $casts = [
        'section_id' => 'integer',
        'date'       => 'date:Y-m-d',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(FlagAttendanceRecord::class, 'homeroom_flag_attendance_date_id');
    }
}
