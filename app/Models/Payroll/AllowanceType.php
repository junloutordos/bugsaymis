<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllowanceType extends Model
{
    protected $table = 'allowance_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'is_taxable',
        'is_mandatory',
        'is_fixed_amount',
        'default_amount',
        'applicable_employment_types',
        'sg_min',
        'sg_max',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_taxable'                 => 'boolean',
        'is_mandatory'               => 'boolean',
        'is_fixed_amount'            => 'boolean',
        'default_amount'             => 'decimal:4',
        'applicable_employment_types' => 'array',
        'is_active'                  => 'boolean',
    ];

    public function payrollAllowances(): HasMany
    {
        return $this->hasMany(PayrollAllowance::class);
    }

    public function payrollDeductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class);
    }

    public function employeeConfigs(): HasMany
    {
        return $this->hasMany(EmployeeDeductionConfig::class);
    }
}
