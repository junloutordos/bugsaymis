<?php

namespace App\Models\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostSubStrategy;
use App\Models\PerformanceIndicator;
use Illuminate\Database\Eloquent\Model;

class OpcrIndicator extends Model
{
    protected $fillable = [
        'fiscal_year',
        'dost_sub_strategy_id',
        'agency_outcome_id',
        'performance_indicator_id',
        'description',
        'target',
        'budget',
        'remarks',
        'accomplishment',
        'rating_quality',
        'rating_efficiency',
        'rating_timeliness',
        'rating_average',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'rating_quality' => 'decimal:2',
        'rating_efficiency' => 'decimal:2',
        'rating_timeliness' => 'decimal:2',
        'rating_average' => 'decimal:2',
    ];

    protected $appends = ['accomplishment_summary', 'displayed_accomplishment'];

    public function scopeForFiscalYear($query, int $year)
    {
        return $query->where('fiscal_year', $year);
    }

    public function subStrategy()
    {
        return $this->belongsTo(DostSubStrategy::class, 'dost_sub_strategy_id');
    }

    public function agencyOutcome()
    {
        return $this->belongsTo(AgencyOutcome::class, 'agency_outcome_id');
    }

    public function performanceIndicator()
    {
        return $this->belongsTo(PerformanceIndicator::class, 'performance_indicator_id');
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'opcr_indicator_divisions');
    }

    public function actuals()
    {
        return $this->hasMany(OpcrIndicatorActual::class);
    }

    // Actual accomplishment is free text per quarter (percentages, counts, or
    // narrative notes), so the only universally-safe summary is a labeled
    // join of the quarters that have a value — never a numeric sum/average.
    public function getAccomplishmentSummaryAttribute(): ?string
    {
        if (! $this->relationLoaded('actuals')) {
            return null;
        }

        $parts = collect(range(1, 4))
            ->map(fn ($quarter) => [$quarter, $this->actuals->firstWhere('quarter', $quarter)?->value])
            ->filter(fn ($pair) => filled($pair[1]))
            ->map(fn ($pair) => "Q{$pair[0]}: {$pair[1]}");

        return $parts->isEmpty() ? null : $parts->implode('; ');
    }

    // A manually-typed `accomplishment` always wins over the auto-joined
    // Q1-Q4 summary — set once, it stops tracking further Q1-Q4 edits until
    // cleared back to null (see OpcrIndicatorController::updateAccomplishment).
    public function getDisplayedAccomplishmentAttribute(): ?string
    {
        return filled($this->accomplishment) ? $this->accomplishment : $this->accomplishment_summary;
    }
}
