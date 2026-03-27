<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $fillable = [
        'code',
        'name',
        'description',
        'credit_units',
        'lecture_hours',
        'lab_hours',
        'load_units',
        'subject_type',
        'grade_level',
        'semester',
        'sessions_per_week',
        'minutes_per_session',
        'is_active',
    ];

    protected $casts = [
        'credit_units'        => 'integer',
        'lecture_hours'       => 'decimal:1',
        'lab_hours'           => 'decimal:1',
        'load_units'          => 'decimal:2',
        'grade_level'         => 'integer',
        'sessions_per_week'   => 'integer',
        'minutes_per_session' => 'integer',
        'is_active'           => 'boolean',
    ];

    public function loadAssignments(): HasMany
    {
        return $this->hasMany(LoadAssignment::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    /** Total contact hours per week (lecture + lab) */
    public function getTotalHoursAttribute(): float
    {
        return (float) $this->lecture_hours + (float) $this->lab_hours;
    }

    /** Total contact minutes per week (sessions × duration) */
    public function getTotalMinutesPerWeekAttribute(): int
    {
        return $this->sessions_per_week * $this->minutes_per_session;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGrade($query, int $grade)
    {
        return $query->where(function ($q) use ($grade) {
            $q->where('grade_level', $grade)
              ->orWhere('grade_level', 0); // cross-grade electives
        });
    }
}
