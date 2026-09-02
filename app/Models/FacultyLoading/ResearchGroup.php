<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchGroup extends Model
{
    protected $table = 'research_groups';

    protected $fillable = [
        'academic_term_id',
        'grade_level',
        'title',
        'research_type',
    ];

    protected $casts = [
        'grade_level' => 'integer',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function advisories(): HasMany
    {
        return $this->hasMany(ResearchAdvisory::class);
    }

    /** True if at least one non-dropped advisory row belongs to this group. */
    public function scopeActive($query)
    {
        return $query->whereHas('advisories', fn ($q) => $q->where('status', '<>', 'dropped'));
    }
}
