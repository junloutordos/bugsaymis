<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiScheduleJob extends Model
{
    protected $table = 'ai_schedule_jobs';

    protected $fillable = [
        'school_year_id',
        'academic_term_id',
        'status',
        'parameters',
        'fitness_score',
        'hard_conflicts',
        'schedules_generated',
        'generated_schedules',
        'replaced_schedules',
        'error_message',
        'started_at',
        'completed_at',
        'restored_at',
        'created_by',
    ];

    protected $casts = [
        'parameters'          => 'array',
        'fitness_score'       => 'integer',
        'hard_conflicts'      => 'integer',
        'schedules_generated' => 'integer',
        'generated_schedules' => 'array',
        'replaced_schedules'  => 'array',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'restored_at'         => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasConflicts(): bool
    {
        return $this->hard_conflicts > 0;
    }

    /** Duration of the GA run in seconds, or null if not completed. */
    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }
        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForTerm($query, int $termId)
    {
        return $query->where('academic_term_id', $termId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
