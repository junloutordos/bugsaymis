<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    protected $table = 'academic_terms';

    protected $fillable = [
        'school_year_id',
        'name',
        'term_type',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function facultyLoads(): HasMany
    {
        return $this->hasMany(FacultyLoad::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /** Human-readable label: "1st Semester, S.Y. 2025-2026" */
    public function getFullLabelAttribute(): string
    {
        return $this->name . ', S.Y. ' . $this->schoolYear->name;
    }
}
