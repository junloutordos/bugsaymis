<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyVacancy extends Model
{
    protected $table = 'faculty_vacancies';

    protected $fillable = [
        'school_year_id',
        'academic_term_id',
        'academic_unit_id',
        'sst_position_id',
        'subject_id',
        'status',
        'filled_by_user_id',
        'filled_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'filled_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function sstPosition(): BelongsTo
    {
        return $this->belongsTo(SstPosition::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function filledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeFilled($query)
    {
        return $query->where('status', 'filled');
    }

    public function scopeForSchoolYear($query, int $schoolYearId)
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function markFilled(User $user): void
    {
        $this->update([
            'status'           => 'filled',
            'filled_by_user_id' => $user->id,
            'filled_at'        => now(),
        ]);
    }
}
