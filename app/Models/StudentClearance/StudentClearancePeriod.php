<?php

namespace App\Models\StudentClearance;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentClearancePeriod extends Model
{
    protected $fillable = [
        'school_year_id',
        'title',
        'opens_at',
        'closes_at',
        'status',
        'target_grade_levels',
        'created_by',
    ];

    protected $casts = [
        'school_year_id'       => 'integer',
        'opens_at'             => 'date:Y-m-d',
        'closes_at'            => 'date:Y-m-d',
        'target_grade_levels'  => 'array',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(StudentClearance::class);
    }
}
