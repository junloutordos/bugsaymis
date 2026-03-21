<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndividualDevelopmentPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'individual_development_plans';

    protected $fillable = [
        'employee_id',
        'supervisor_id',
        'training_need_id',
        'learning_program_id',
        'year',
        'competency',
        'current_level',
        'target_level',
        'development_activity',
        'intervention_type',
        'timeline_start',
        'timeline_end',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'supervisor_remarks',
        'employee_remarks',
    ];

    protected $casts = [
        'year'           => 'integer',
        'timeline_start' => 'date',
        'timeline_end'   => 'date',
        'approved_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function trainingNeed(): BelongsTo
    {
        return $this->belongsTo(TrainingNeed::class, 'training_need_id');
    }

    public function learningProgram(): BelongsTo
    {
        return $this->belongsTo(LearningProgram::class, 'learning_program_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForSupervisor($query, int $supervisorId)
    {
        return $query->where('supervisor_id', $supervisorId);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByIntervention($query, string $type)
    {
        return $query->where('intervention_type', $type);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->timeline_end
            && $this->timeline_end->isPast()
            && !in_array($this->status, ['completed', 'cancelled']);
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->timeline_end) return null;
        if ($this->timeline_end->isPast()) return 0;
        return (int) now()->diffInDays($this->timeline_end);
    }

    public function getProgressLabelAttribute(): string
    {
        return match ($this->status) {
            'planned'   => 'Not Started',
            'ongoing'   => 'In Progress',
            'completed' => 'Completed',
            'deferred'  => 'Deferred',
            'cancelled' => 'Cancelled',
            default     => 'Unknown',
        };
    }

    public function getApprovalLabelAttribute(): string
    {
        return match ($this->approval_status) {
            'draft'     => 'Draft',
            'submitted' => 'Pending Approval',
            'approved'  => 'Approved',
            'returned'  => 'Returned for Revision',
            default     => 'Unknown',
        };
    }
}
