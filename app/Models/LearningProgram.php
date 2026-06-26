<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningProgram extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'type',
        'competency_area',
        'target_position',
        'provider',
        'start_date',
        'end_date',
        'hours',
        'budget',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'hours'      => 'integer',
        'budget'     => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function individualDevelopmentPlans(): HasMany
    {
        return $this->hasMany(IndividualDevelopmentPlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['planned', 'ongoing']);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('start_date', $year);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getTotalParticipantsAttribute(): int
    {
        return $this->sessions()
            ->withCount('participants')
            ->get()
            ->sum('participants_count');
    }

    public function getDurationDaysAttribute(): ?int
    {
        if (!$this->start_date || !$this->end_date) return null;
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
