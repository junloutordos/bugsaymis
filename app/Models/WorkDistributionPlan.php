<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkDistributionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_indicator_id',
        'success_indicator',
        'office_involved',
        'free_text_involved',
        'rated_by',
        'load_source',
        'fiscal_year',
    ];

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

    // Many offices can be involved in a plan
    public function offices()
    {
        return $this->belongsToMany(\App\Models\Office::class, 'office_work_distribution_plan');
    }

    public function committees()
    {
        return $this->belongsToMany(\App\Models\Committee::class, 'committee_work_distribution_plan');
    }

    public function specialAssignments()
    {
        return $this->belongsToMany(\App\Models\SpecialAssignment::class, 'special_assignment_work_distribution_plan');
    }

    // Each plan belongs to one performance indicator
    public function performanceIndicator()
    {
        return $this->belongsTo(PerformanceIndicator::class);
    }

    // Each plan has many assigned personnel (users)
    public function personnel()
    {
        return $this->belongsToMany(User::class, 'plan_user')
            ->withTimestamps();
    }
    // Each plan has many assigned personnel (users)
    public function users()
    {
        return $this->belongsToMany(User::class, 'plan_user') // 👈 make sure pivot matches migration
            ->withTimestamps();
    }

    public function employeeipcrs()
    {
        return $this->belongsToMany(EmployeeIPCR::class, 'employee_ipcrs_plan', 'plan_id', 'ipcr_id');
    }
    public function performance_indicator()
    {
        return $this->belongsTo(PerformanceIndicator::class, 'performance_indicator_id')
                    ->with('agencyOutcome');;
    }

}
