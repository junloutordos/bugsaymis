<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRequirement extends Model
{
    protected $table = 'research_requirements';

    protected $fillable = [
        'created_by',
        'academic_term_id',
        'title',
        'description',
        'research_type',
        'grade_levels',
        'accepted_file_types',
        'max_files',
        'due_at',
        'allow_late_submission',
        'status',
    ];

    protected $casts = [
        'grade_levels'          => 'array',
        'max_files'             => 'integer',
        'due_at'                => 'datetime',
        'allow_late_submission' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ResearchRequirementAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
