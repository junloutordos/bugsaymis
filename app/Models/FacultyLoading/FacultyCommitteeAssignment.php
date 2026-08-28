<?php

namespace App\Models\FacultyLoading;

use App\Models\Committee;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacultyCommitteeAssignment extends Model
{
    protected $table = 'faculty_committee_assignments';

    protected $fillable = [
        'user_id',
        'school_year_id',
        'academic_term_id',
        'committee_id',
        'committee_name',
        'role',
        'load_units',
        'status',
        'remarks',
        'load_assignment_id',
    ];

    protected $casts = [
        'load_units' => 'decimal:2',
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

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function loadAssignment(): BelongsTo
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function accomplishments(): HasMany
    {
        return $this->hasMany(FacultyCommitteeAccomplishment::class, 'faculty_committee_assignment_id');
    }

    public function workDistributionPlans(): BelongsToMany
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'faculty_committee_assignment_work_distribution_plan')
            ->withTimestamps();
    }

    /**
     * True when this committee assignment carries an actual unit load (> 0)
     * — the signal used to auto-classify its linked Work Distribution Plans
     * as Core Functions on IPCR. A zero/null load_units assignment (a
     * committee assignment "without load") is treated as a Support Function.
     */
    public function hasUnitLoad(): bool
    {
        return (float) ($this->load_units ?? 0) > 0;
    }

    public function isChairperson(): bool
    {
        return in_array($this->role, ['chairperson', 'co_chair']);
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
