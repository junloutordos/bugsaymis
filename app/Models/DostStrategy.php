<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DostStrategy extends Model
{
    protected $fillable = ['dost_pillar_id', 'name'];

    public function pillar()
    {
        return $this->belongsTo(DostPillar::class, 'dost_pillar_id');
    }

    public function agencyOutcomes()
    {
        return $this->belongsToMany(AgencyOutcome::class, 'dost_strategy_agency_outcomes');
    }

    public function subStrategies()
    {
        return $this->hasMany(DostSubStrategy::class);
    }
}
