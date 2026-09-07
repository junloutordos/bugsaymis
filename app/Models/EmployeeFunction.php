<?php

namespace App\Models;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use Illuminate\Database\Eloquent\Model;

class EmployeeFunction extends Model
{
    protected $table = 'employee_functions';

    public const TYPE_CORE = 'core';
    public const TYPE_SUPPORT = 'support';

    public const SOURCE_LOAD_ASSIGNMENT = 'load_assignment';
    public const SOURCE_WDP = 'wdp';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'user_id', 'function_type', 'source_type',
        'load_assignment_id',
        'label', 'weight_percent', 'academic_term_id', 'created_by',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loadAssignment()
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function workDistributionPlans()
    {
        return $this->belongsToMany(
            WorkDistributionPlan::class,
            'employee_function_work_distribution_plan'
        );
    }

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ipcrV2CoreItems()
    {
        return $this->hasMany(\App\Models\IPCRV2\IpcrV2CoreItem::class);
    }

    public function scopeCore($query)
    {
        return $query->where('function_type', self::TYPE_CORE);
    }

    public function scopeSupport($query)
    {
        return $query->where('function_type', self::TYPE_SUPPORT);
    }

    public function scopeAutoSynced($query)
    {
        return $query->where('source_type', self::SOURCE_LOAD_ASSIGNMENT);
    }
}
