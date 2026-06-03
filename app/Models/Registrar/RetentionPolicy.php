<?php

namespace App\Models\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPolicy extends Model
{
    protected $table = 'retention_policies';

    protected $fillable = [
        'name',
        'school_year_id',
        'grade_band',
        'failed_subjects_for_retention',
        'failed_subjects_for_exclusion',
        'min_gwa_for_promotion',
        'honors_highest_cutoff',
        'honors_high_cutoff',
        'honors_regular_cutoff',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'school_year_id'                => 'integer',
        'failed_subjects_for_retention' => 'integer',
        'failed_subjects_for_exclusion' => 'integer',
        'min_gwa_for_promotion'         => 'decimal:2',
        'honors_highest_cutoff'         => 'decimal:2',
        'honors_high_cutoff'            => 'decimal:2',
        'honors_regular_cutoff'         => 'decimal:2',
        'is_active'                     => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Determine the honors label for a given GWA.
     */
    public function honorsLabel(float $gwa): string
    {
        if ($gwa >= (float) $this->honors_highest_cutoff) {
            return 'With Highest Honors';
        }
        if ($gwa >= (float) $this->honors_high_cutoff) {
            return 'With High Honors';
        }
        if ($gwa >= (float) $this->honors_regular_cutoff) {
            return 'With Honors';
        }
        return 'None';
    }

    /**
     * Determine academic standing based on failed subject count and GWA.
     * Returns: 'promoted' | 'retained' | 'excluded'
     */
    public function determineStanding(int $failedCount, float $gwa): string
    {
        if ($this->failed_subjects_for_exclusion !== null
            && $failedCount >= $this->failed_subjects_for_exclusion) {
            return 'excluded';
        }

        if ($failedCount >= $this->failed_subjects_for_retention) {
            return 'retained';
        }

        if ($this->min_gwa_for_promotion !== null
            && $gwa < (float) $this->min_gwa_for_promotion) {
            return 'retained';
        }

        return 'promoted';
    }

    public function gradeBandLabel(): string
    {
        return match ($this->grade_band) {
            'jhs' => 'Junior High School (Gr. 7–10)',
            'shs' => 'Senior High School (Gr. 11–12)',
            default => 'All Grade Levels',
        };
    }
}
