<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\FacultyLoading\Section;

class LoadAssignment extends Model
{
    protected $table = 'load_assignments';

    protected $fillable = [
        'faculty_load_id',
        'user_id',
        'school_year_id',
        'academic_term_id',
        'assignment_type',
        'subject_id',
        'section_id',
        'load_units',
        'is_overridden',
        'description',
        'designation_id',
        'created_by',
    ];

    protected $casts = [
        'load_units'   => 'decimal:2',
        'is_overridden'=> 'boolean',
        'section_id'   => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function facultyLoad(): BelongsTo
    {
        return $this->belongsTo(FacultyLoad::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function researchAdvisory(): HasOne
    {
        return $this->hasOne(ResearchAdvisory::class);
    }

    public function committeeAssignment(): HasOne
    {
        return $this->hasOne(FacultyCommitteeAssignment::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function workDistributionPlans(): BelongsToMany
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'load_assignment_work_distribution_plan')
            ->withTimestamps();
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOfType($query, string $type)
    {
        return $query->where('assignment_type', $type);
    }

    public function scopeTeaching($query)
    {
        return $query->where('assignment_type', 'teaching');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isTeaching(): bool
    {
        return $this->assignment_type === 'teaching';
    }

    /**
     * True when this assignment carries an actual unit load (> 0) — the
     * signal used to auto-classify its linked Work Distribution Plans as
     * Core Functions on IPCR. A zero/null load_units assignment is treated
     * as a Support Function.
     */
    public function hasUnitLoad(): bool
    {
        return (float) ($this->load_units ?? 0) > 0;
    }

    public function getDisplayLabelAttribute(): string
    {
        if ($this->isTeaching() && $this->subject) {
            return $this->subject->name;
        }
        return $this->description ?? ucfirst($this->assignment_type);
    }
}
