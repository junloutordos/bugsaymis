<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'coordinator_id', 'description'];

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
