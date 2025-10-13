<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceIndicator extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'agency_outcome_id',
        'description',
        'target',
        'budget',
    ];

    public function agencyOutcome()
    {
        return $this->belongsTo(AgencyOutcome::class, 'agency_outcome_id','id');
    }

     // ✅ Use only many-to-many
    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'division_performance_indicator');
    }

}
