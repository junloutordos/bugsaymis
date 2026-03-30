<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchAdvisory extends Model
{
    protected $table = 'research_advisories';

    protected $fillable = [
        'user_id',
        'school_year_id',
        'academic_term_id',
        'student_id',
        'student_name',
        'research_title',
        'grade_level',
        'advisory_type',
        'load_units',
        'status',
        'remarks',
        'load_assignment_id',
    ];

    protected $casts = [
        'grade_level' => 'integer',
        'load_units'  => 'decimal:2',
        'student_id'  => 'integer',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function loadAssignment(): BelongsTo
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForFaculty($query, int $userId, int $termId)
    {
        return $query->where('user_id', $userId)
                     ->where('academic_term_id', $termId)
                     ->where('status', 'active');
    }
}
