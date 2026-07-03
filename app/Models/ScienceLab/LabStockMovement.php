<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabStockMovement extends Model
{
    protected $table = 'lab_stock_movements';

    protected $fillable = [
        'lab_consumable_id', 'lot_id', 'transaction_date', 'reference_type', 'reference_id', 'reference_number', 'qty_received', 'qty_issued', 'qty_returned', 'balance_after', 'unit_cost', 'remarks', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date:Y-m-d',
        'qty_received' => 'decimal:3',
        'qty_issued' => 'decimal:3',
        'qty_returned' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    public function consumable() { return $this->belongsTo(LabConsumable::class, 'lab_consumable_id'); }

    public function lot() { return $this->belongsTo(LabConsumableLot::class, 'lot_id'); }

    public function creator() { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
