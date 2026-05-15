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
        'payroll_no', 'batch_type', 'period_start', 'period_end',
        'month', 'year', 'fund_cluster', 'entity_name',
        'source_main_filename', 'source_bonus_filename',
        'source_main_s3_key', 'source_bonus_s3_key',
        'uploaded_by', 'status',
        'totals_gross', 'totals_deductions', 'totals_net', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
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
