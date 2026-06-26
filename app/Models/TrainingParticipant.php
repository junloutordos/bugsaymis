<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'employee_id',
        'attendance_status',
        'completion_status',
        'certificate_path',
        'certificate_issued_date',
        'nomination_status',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'certificate_issued_date' => 'date:Y-m-d',
        'approved_at'             => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(TrainingEvaluation::class, 'participant_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeAttended($query)
    {
        return $query->where('attendance_status', 'attended');
    }

    public function scopeCompleted($query)
    {
        return $query->where('completion_status', 'completed');
    }

    public function scopeApproved($query)
    {
        return $query->where('nomination_status', 'approved');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePendingNomination($query)
    {
        return $query->where('nomination_status', 'nominated');
    }

    public function scopeWithCertificate($query)
    {
        return $query->whereNotNull('certificate_path');
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getHasCertificateAttribute(): bool
    {
        return !is_null($this->certificate_path);
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->nomination_status === 'approved';
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->completion_status === 'completed'
            && $this->attendance_status === 'attended';
    }

    public function getProgramAttribute(): ?LearningProgram
    {
        return $this->session?->program;
    }
}
