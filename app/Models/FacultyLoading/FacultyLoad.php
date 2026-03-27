<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FacultyLoad extends Model
{
    protected $table = 'faculty_loads';

    protected $fillable = [
        'user_id',
        'school_year_id',
        'academic_term_id',
        'teaching_units',
        'research_units',
        'admin_units',
        'cocurricular_units',
        'committee_units',
        'total_units',
        'full_load_threshold',
        'load_status',
        'is_locked',
        'overload_approved',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'remarks',
    ];

    protected $casts = [
        'teaching_units'      => 'decimal:2',
        'research_units'      => 'decimal:2',
        'admin_units'         => 'decimal:2',
        'cocurricular_units'  => 'decimal:2',
        'committee_units'     => 'decimal:2',
        'total_units'         => 'decimal:2',
        'full_load_threshold' => 'decimal:2',
        'is_locked'           => 'boolean',
        'overload_approved'   => 'boolean',
        'approved_at'         => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LoadAssignment::class);
    }

    public function overloadComputation(): HasOne
    {
        return $this->hasOne(OverloadComputation::class);
    }

    // ── Computed helpers ─────────────────────────────────────────────────────

    public function getOverloadUnitsAttribute(): float
    {
        $over = (float) $this->total_units - (float) $this->full_load_threshold;
        return max(0.0, $over);
    }

    public function isUnderload(): bool
    {
        return $this->load_status === 'underload';
    }

    public function isFullLoad(): bool
    {
        return $this->load_status === 'full_load';
    }

    public function isOverload(): bool
    {
        return $this->load_status === 'overload';
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForTerm($query, int $termId)
    {
        return $query->where('academic_term_id', $termId);
    }

    public function scopeForSchoolYear($query, int $syId)
    {
        return $query->where('school_year_id', $syId);
    }

    public function scopeOverload($query)
    {
        return $query->where('load_status', 'overload');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('load_status', 'overload')
                     ->where('overload_approved', false);
    }
}
