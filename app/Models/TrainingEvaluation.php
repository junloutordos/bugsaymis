<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_id',

        // Level 1 — Reaction
        'reaction_score',
        'relevance_score',
        'facilitation_score',
        'logistics_score',
        'reaction_feedback',

        // Level 2 — Learning
        'learning_score',
        'learning_feedback',

        // Level 3 — Behavior
        'behavior_score',
        'behavior_assessed_date',
        'behavior_assessed_by',
        'behavior_feedback',

        // Level 4 — Results
        'results_score',
        'results_assessed_date',
        'results_assessed_by',
        'results_feedback',

        // General
        'feedback',
        'recommendations',
        'overall_average',
    ];

    protected $casts = [
        'reaction_score'       => 'integer',
        'relevance_score'      => 'integer',
        'facilitation_score'   => 'integer',
        'logistics_score'      => 'integer',
        'learning_score'       => 'integer',
        'behavior_score'       => 'integer',
        'results_score'        => 'integer',
        'overall_average'      => 'decimal:2',
        'behavior_assessed_date' => 'date:Y-m-d',
        'results_assessed_date'  => 'date:Y-m-d',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'participant_id');
    }

    public function behaviorAssessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'behavior_assessed_by');
    }

    public function resultsAssessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'results_assessed_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeWithReaction($query)
    {
        return $query->whereNotNull('reaction_score');
    }

    public function scopeWithBehavior($query)
    {
        return $query->whereNotNull('behavior_score');
    }

    public function scopeWithResults($query)
    {
        return $query->whereNotNull('results_score');
    }

    public function scopeHighRated($query, float $threshold = 4.0)
    {
        return $query->where('overall_average', '>=', $threshold);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getLevel1AverageAttribute(): ?float
    {
        $scores = array_filter([
            $this->reaction_score,
            $this->relevance_score,
            $this->facilitation_score,
            $this->logistics_score,
        ]);
        if (empty($scores)) return null;
        return round(array_sum($scores) / count($scores), 2);
    }

    public function getComputedAverageAttribute(): ?float
    {
        $scores = array_filter([
            $this->getLevel1AverageAttribute(),
            $this->learning_score,
            $this->behavior_score,
            $this->results_score,
        ]);
        if (empty($scores)) return null;
        return round(array_sum($scores) / count($scores), 2);
    }

    public function getRatingLabelAttribute(): string
    {
        $avg = $this->overall_average ?? $this->getComputedAverageAttribute();
        if ($avg === null) return 'Not Rated';
        return match (true) {
            $avg >= 4.5 => 'Outstanding',
            $avg >= 3.5 => 'Very Satisfactory',
            $avg >= 2.5 => 'Satisfactory',
            $avg >= 1.5 => 'Unsatisfactory',
            default     => 'Poor',
        };
    }

    public function getKirkpatrickLevelsCompletedAttribute(): int
    {
        $levels = 0;
        if ($this->reaction_score !== null)  $levels++;
        if ($this->learning_score !== null)  $levels++;
        if ($this->behavior_score !== null)  $levels++;
        if ($this->results_score !== null)   $levels++;
        return $levels;
    }

    // ── Static Helpers ─────────────────────────────────────────────────────────

    /**
     * Recompute and persist the overall_average for a given evaluation.
     */
    public function recalculateAverage(): void
    {
        $avg = $this->getComputedAverageAttribute();
        if ($avg !== null) {
            $this->update(['overall_average' => $avg]);
        }
    }
}
