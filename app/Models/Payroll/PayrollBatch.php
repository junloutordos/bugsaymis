<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'period_start'            => 'date',
        'period_end'              => 'date',
        'first_half_credit_date'  => 'date',
        'second_half_credit_date' => 'date',
        'credit_date'             => 'date',
        'disbursement_type'       => 'array',
    ];

    public function hasType(string $type): bool
    {
        return in_array($type, (array) $this->disbursement_type);
    }

    public function isMonthly(): bool
    {
        return $this->hasType('monthly_salary');
    }

    /** Human-readable label for a given send pass (first_half / second_half / null for single). */
    public function disbursementLabel(?string $pass = null): string
    {
        if ($this->label) return $this->label;
        return self::buildLabel((array) $this->disbursement_type, $pass);
    }

    public static function buildLabel(array $types, ?string $pass = null): string
    {
        $names = [
            'monthly_salary' => 'Monthly Salary',
            'hazard_pay'     => 'Hazard Pay',
            'sala'           => 'Subsistence Allowance',
            'longevity_pay'  => 'Longevity Pay',
            'other'          => 'Other Allowance',
        ];

        if ($pass === 'first_half') return '1st Half Salary';

        $parts = [];
        foreach ($types as $t) {
            if ($pass === 'second_half' && $t === 'monthly_salary') {
                $parts[] = '2nd Half Salary';
            } else {
                $parts[] = $names[$t] ?? ucwords(str_replace('_', ' ', $t));
            }
        }
        return implode(' + ', $parts);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Primary items (batch_id FK — used for unmatched rows and legacy queries). */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'batch_id');
    }

    /** All items linked to this batch via pivot (matched + merged across types). */
    public function linkedItems(): BelongsToMany
    {
        return $this->belongsToMany(PayrollItem::class, 'payroll_batch_items', 'batch_id', 'item_id');
    }
}
