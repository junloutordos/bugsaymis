<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeductionConfig extends Model
{
    protected $table = 'employee_deduction_configs';

    protected $fillable = [
        'user_id',
        'allowance_type_id',
        'amount',
        'reference_no',
        'is_active',
        'effective_from',
        'effective_to',
        'note',
    ];

    protected $casts = [
        'amount'         => 'decimal:4',
        'is_active'      => 'boolean',
        'effective_from' => 'date:Y-m-d',
        'effective_to'   => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class);
    }
}
