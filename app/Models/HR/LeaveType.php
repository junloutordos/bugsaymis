<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\HR\LeaveTypeFactory::new();
    }

    protected $table = 'leave_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'days_per_year',
        'auto_grant_annual',
        'is_creditable',
        'is_deductible',
        'requires_approval',
        'min_days_notice',
        'max_days_per_application',
        'with_pay',
        'applicable_employment_types',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'days_per_year'              => 'decimal:2',
        'auto_grant_annual'          => 'boolean',
        'max_days_per_application'   => 'decimal:2',
        'is_creditable'              => 'boolean',
        'is_deductible'              => 'boolean',
        'requires_approval'          => 'boolean',
        'with_pay'                   => 'boolean',
        'applicable_employment_types' => 'array',
        'is_active'                  => 'boolean',
    ];

    public function leaveCredits(): HasMany
    {
        return $this->hasMany(LeaveCredit::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }
}
