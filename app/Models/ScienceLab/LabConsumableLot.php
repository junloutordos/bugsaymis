<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabConsumableLot extends Model
{
    protected $table = 'lab_consumable_lots';

    protected $fillable = [
        'lab_consumable_id', 'lot_no', 'quantity', 'received_date', 'expiry_date', 'unit_cost', 'status', 'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'received_date' => 'date:Y-m-d',
        'expiry_date' => 'date:Y-m-d',
        'unit_cost' => 'decimal:4',
    ];

    public function consumable() { return $this->belongsTo(LabConsumable::class, 'lab_consumable_id'); }
}
