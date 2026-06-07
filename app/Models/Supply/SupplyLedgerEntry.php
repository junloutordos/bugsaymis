<?php

namespace App\Models\Supply;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyLedgerEntry extends Model
{
    protected $table = 'supply_ledger_entries';

    protected $fillable = [
        'supply_item_id', 'transaction_date', 'reference_type', 'reference_id',
        'reference_number', 'qty_received', 'qty_issued', 'qty_returned',
        'balance_after', 'unit_cost', 'total_cost', 'remarks', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'qty_received'     => 'decimal:3',
        'qty_issued'       => 'decimal:3',
        'qty_returned'     => 'decimal:3',
        'balance_after'    => 'decimal:3',
        'unit_cost'        => 'decimal:4',
        'total_cost'       => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
