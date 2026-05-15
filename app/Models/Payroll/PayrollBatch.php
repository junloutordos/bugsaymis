<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollBatch extends Model
{
    protected $table = 'payroll_batches';

    protected $fillable = [
        'payroll_no', 'batch_type', 'disbursement_type', 'label',
        'period_start', 'period_end', 'month', 'year',
        'fund_cluster', 'entity_name',
        'source_main_filename', 'source_main_s3_key',
        'uploaded_by', 'status',
        'first_half_credit_date', 'second_half_credit_date', 'credit_date',
        'totals_gross', 'totals_deductions', 'totals_net', 'notes',
    ];

    protected $casts = [
        'period_start'           => 'date',
        'period_end'             => 'date',
        'first_half_credit_date' => 'date',
        'second_half_credit_date'=> 'date',
        'credit_date'            => 'date',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'batch_id');
    }
}
