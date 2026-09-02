<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchAdvisory extends Model
{
    protected $table = 'research_advisories';

    protected $fillable = [
        'user_id',
        'school_year_id',
        'academic_term_id',
        'research_title',
        'grade_level',
        'advisory_role',
        'research_type',
        'load_units',
        'status',
        'remarks',
        'load_assignment_id',
        'research_group_id',
    ];

    protected $casts = [
        'grade_level' => 'integer',
        'load_units'  => 'decimal:2',
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

    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ResearchAdvisoryMember::class);
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
