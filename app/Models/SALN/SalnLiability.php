<?php

namespace App\Models\SALN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnLiability extends Model
{
    protected $table = 'saln_liabilities';

    protected $fillable = [
        'saln_record_id',
        'nature',
        'nature_other',
        'creditor_name',
        'outstanding_balance',
    ];

    protected $casts = [
        'outstanding_balance' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function salnRecord(): BelongsTo
    {
        return $this->belongsTo(SalnRecord::class, 'saln_record_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getNatureLabel(): string
    {
        return match ($this->nature) {
            'housing_loan'    => 'Housing Loan',
            'car_loan'        => 'Car Loan',
            'personal_loan'   => 'Personal Loan',
            'credit_card'     => 'Credit Card',
            'gsis_policy_loan'=> 'GSIS Policy Loan',
            'pagibig_loan'    => 'Pag-IBIG Loan',
            'sss_loan'        => 'SSS Loan',
            'business_loan'   => 'Business Loan',
            'others'          => $this->nature_other ?? 'Others',
            default           => ucfirst($this->nature),
        };
    }
}
