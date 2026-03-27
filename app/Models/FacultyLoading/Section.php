<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a student section/class at PSHS.
 *
 * Maps to the existing `sections` table (created in migration
 * 2026_01_21_000200). The legacy schema uses non-standard column
 * names; the Faculty Loading module adds section_code, strand,
 * capacity, and is_active via migration 2026_03_27_000011.
 *
 * Legacy → FL column mapping:
 *   levelid     → grade level (integer)
 *   sectionname → section name (string)
 *   syid        → school year ID (raw int, no FK)
 *   adviser     → adviser user ID (raw int)
 */
class Section extends Model
{
    protected $table    = 'sections';
    protected $keyType  = 'int';       // INT primary key (not BigInt)
    public    $timestamps = false;     // legacy table has no timestamps

    protected $fillable = [
        'levelid',
        'sectionname',
        'section_code',
        'strand',
        'capacity',
        'adviser',
        'syid',
        'is_active',
        'permission',
        'asstadviser',
    ];

    protected $casts = [
        'levelid'   => 'integer',
        'syid'      => 'integer',
        'capacity'  => 'integer',
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    /** School year (via legacy syid column). */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'syid');
    }

    /** Section adviser (via legacy adviser column). */
    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser');
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'section_id');
    }

    public function loadAssignments(): HasMany
    {
        return $this->hasMany(LoadAssignment::class, 'section_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGrade($query, int $grade)
    {
        return $query->where('levelid', $grade);
    }

    public function scopeForSchoolYear($query, int $schoolYearId)
    {
        return $query->where('syid', $schoolYearId);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    /** Canonical grade level (maps legacy levelid). */
    public function getGradeLevelAttribute(): int
    {
        return (int) $this->levelid;
    }

    /** Canonical name (maps legacy sectionname). */
    public function getNameAttribute(): string
    {
        return (string) $this->sectionname;
    }

    /** e.g., "Grade 7 — Newton" */
    public function getFullLabelAttribute(): string
    {
        return "Grade {$this->grade_level} — {$this->sectionname}";
    }
}
