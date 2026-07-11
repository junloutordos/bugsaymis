<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'coordinator_id', 'description', 'fiscal_year'];

    // NULL fiscal_year = applies to all years (legacy rows)
    public function scopeForFiscalYear($query, ?int $year)
    {
        if (! $year) {
            return $query;
        }

        return $query->where(function ($q) use ($year) {
            $q->whereNull('fiscal_year')->orWhere('fiscal_year', $year);
        });
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'special_assignment_user')->withPivot('task');
    }

    public function workDistributionPlans()
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'special_assignment_work_distribution_plan');
    }
}
