<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    protected $table = 'payroll_items';

    protected $fillable = [
        'batch_id', 'bonus_batch_id', 'month', 'year',
        'excel_row_number', 'employee_name_raw',
        'matched_user_id', 'match_status',
        'employee_no', 'position', 'office_division',
        'basic_salary', 'salary_increase', 'additional_compensation',
        'pera', 'sala', 'hazard_pay', 'longevity_pay', 'others_bonuses', 'gross_earnings',
        'lawop', 'pvp_overpayment', 'gsis_contribution', 'bir_tax',
        'wht_hazard', 'wht_cna', 'longevity_tax',
        'philhealth_contribution', 'philhealth_differential',
        'hdmf_contribution', 'hdmf_additional',
        'gsis_salary_loan', 'gsis_policy_loan', 'gsis_consolidated_loan',
        'gsis_emergency_loan', 'gsis_mpl', 'gsis_gfal', 'gsis_cpl', 'gsis_mpl_lite',
        'hdmf_salary_loan', 'hdmf_calamity', 'hdmf_housing', 'hdmf_multipurpose', 'hdmf_mp2',
        'landbank_loan', 'credit_union',
        'total_deductions', 'net_pay', 'first_half_amount', 'second_half_amount',
        'adjustments_json', 'raw_row_json', 'bonus_uploaded_at',
    ];

    protected $casts = [
        'adjustments_json'  => 'array',
        'raw_row_json'      => 'array',
        'bonus_uploaded_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'batch_id');
    }

    public function bonusBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'bonus_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(PayrollEmail::class, 'payroll_item_id');
    }

    public function latestEmail(): HasMany
    {
        return $this->hasMany(PayrollEmail::class, 'payroll_item_id')->latest();
    }
}
