<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingNeed extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'assessed_by',
        'competency_area',
        'competency_gap',
        'current_level',
        'target_level',
        'priority_level',
        'recommended_training',
        'source',
        'year',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'year'        => 'integer',
        'approved_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function individualDevelopmentPlan(): HasOne
    {
        return $this->hasOne(IndividualDevelopmentPlan::class, 'training_need_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority_level', 'high');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeUnaddressed($query)
    {
        return $query->whereIn('status', ['pending', 'approved']);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getGapLevelAttribute(): string
    {
        $levels = ['none' => 0, 'basic' => 1, 'intermediate' => 2, 'advanced' => 3];
        $current = $levels[$this->current_level] ?? 0;
        $target  = $levels[$this->target_level]  ?? 1;
        $gap     = $target - $current;

        return match (true) {
            $gap >= 2 => 'Wide',
            $gap === 1 => 'Moderate',
            default    => 'Minimal',
        };
    }

    public function getIsAddressedAttribute(): bool
    {
        return $this->status === 'addressed';
    }

    public function getHasIdpAttribute(): bool
    {
        return $this->individualDevelopmentPlan()->exists();
    }
}
