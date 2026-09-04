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
        'opcr_period_id',
        'dost_sub_strategy_id',
        'agency_outcome_id',
        'performance_indicator_id',
        'description',
        'target',
        'budget',
        'remarks',
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

    public function period()
    {
        return $this->belongsTo(OpcrPeriod::class, 'opcr_period_id');
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
}
