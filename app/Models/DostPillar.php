<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DostPillar extends Model
{
    protected $fillable = ['name', 'outcome_statement'];

    public function strategies()
    {
        return $this->hasMany(DostStrategy::class);
    }

    public function agencyOutcomes()
    {
        return $this->belongsToMany(AgencyOutcome::class, 'dost_pillar_agency_outcomes');
    }
}
