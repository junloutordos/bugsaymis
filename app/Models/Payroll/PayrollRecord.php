<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRecord extends Model
{
    protected $table = 'payroll_records';

    protected $fillable = [
        'payroll_run_id',
        'user_id',
        'salary_grade',
        'step',
        'monthly_salary',
        'basic_pay',
        'days_worked',
        'days_absent',
        'late_minutes',
        'undertime_minutes',
        'overtime_minutes',
        'tardiness_deduction',
        'absence_deduction',
        'lwop_deduction',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'withheld_tax',
        'status',
        'is_on_leave_without_pay',
        'payslip_path',
    ];

    protected $casts = [
        'monthly_salary'       => 'decimal:4',
        'basic_pay'            => 'decimal:4',
        'days_worked'          => 'decimal:2',
        'days_absent'          => 'decimal:2',
        'late_minutes'         => 'decimal:2',
        'undertime_minutes'    => 'decimal:2',
        'overtime_minutes'     => 'decimal:2',
        'tardiness_deduction'  => 'decimal:4',
        'absence_deduction'    => 'decimal:4',
        'lwop_deduction'       => 'decimal:4',
        'gross_pay'            => 'decimal:4',
        'total_deductions'     => 'decimal:4',
        'net_pay'              => 'decimal:4',
        'withheld_tax'         => 'decimal:4',
        'is_on_leave_without_pay' => 'boolean',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(PayrollAllowance::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class);
    }
}
