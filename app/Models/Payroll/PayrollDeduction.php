<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDeduction extends Model
{
    protected $table = 'payroll_deductions';

    protected $fillable = [
        'payroll_record_id',
        'allowance_type_id',
        'amount',
        'reference_no',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
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
