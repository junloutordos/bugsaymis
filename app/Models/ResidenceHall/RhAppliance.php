<?php

namespace App\Models\ResidenceHall;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RhAppliance extends Model
{
    protected $table = 'rh_appliances';

    protected $fillable = [
        'rh_intern_id',
        'device_type',
        'device_name',
        'unit_count',
        'wattage',
        'fee_amount',
        'is_approved',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_approved'  => 'boolean',
            'fee_amount'   => 'decimal:2',
            'approved_at'  => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(RhIntern::class, 'rh_intern_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
