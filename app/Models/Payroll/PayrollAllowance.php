<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAllowance extends Model
{
    protected $table = 'payroll_allowances';

    protected $fillable = [
        'payroll_record_id',
        'allowance_type_id',
        'amount',
        'is_taxable',
        'note',
    ];

    protected $casts = [
        'amount'     => 'decimal:4',
        'is_taxable' => 'boolean',
    ];

    public function payrollRecord(): BelongsTo
    {
        return $this->belongsTo(PayrollRecord::class);
    }

    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class);
    }
}
