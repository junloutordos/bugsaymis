<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DostStrategy extends Model
{
    protected $fillable = ['dost_pillar_id', 'agency_outcome_id', 'name'];

    public function pillar()
    {
        return $this->belongsTo(DostPillar::class, 'dost_pillar_id');
    }

    public function agencyOutcome()
    {
        return $this->belongsTo(AgencyOutcome::class, 'agency_outcome_id');
    }

    public function subStrategies()
    {
        return $this->hasMany(DostSubStrategy::class);
    }
}
