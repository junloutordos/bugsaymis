<?php

namespace App\Models\ResidenceHall;

use Illuminate\Database\Eloquent\Model;

class RhFeeLedger extends Model
{
    protected $table = 'rh_fee_ledger';

    protected $appends = ['period_label'];

    protected $fillable = [
        'rh_intern_id',
        'period_month',
        'period_year',
        'lodging_fee',
        'appliance_fee',
        'total_fee',
        'is_paid',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'lodging_fee'   => 'decimal:2',
            'appliance_fee' => 'decimal:2',
            'total_fee'     => 'decimal:2',
            'is_paid'       => 'boolean',
            'paid_at'       => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(RhIntern::class, 'rh_intern_id');
    }

    public function getPeriodLabelAttribute(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->period_month, 1, $this->period_year));
    }
}
