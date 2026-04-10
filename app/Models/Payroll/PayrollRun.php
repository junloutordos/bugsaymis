<?php

namespace App\Models\Payroll;

use App\Models\User;
use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use SoftDeletes, HasApprovalSnapshots;

    protected $table = 'payroll_runs';

    protected $fillable = [
        'control_no',
        'year',
        'month',
        'period',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'employee_count',
        'total_gross',
        'total_deductions',
        'total_net',
        'prepared_by',
        'approved_by',
        'approved_at',
        'remarks',
        'job_batch_id',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'pay_date'        => 'date',
        'approved_at'     => 'datetime',
        'total_gross'     => 'decimal:4',
        'total_deductions' => 'decimal:4',
        'total_net'       => 'decimal:4',
    ];

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->control_no)) {
                $model->control_no = sprintf(
                    'PAY-%d-%02d-%s',
                    $model->year,
                    $model->month,
                    $model->period === '1st' ? 'P1' : 'P2'
                );
            }
        });
    }
}
