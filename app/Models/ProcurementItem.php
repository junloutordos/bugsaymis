<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementItem extends Model
{
    protected $table = 'procurement_items';

    protected $fillable = [
        'procurement_id', 'pr_no', 'ppmp_line_item_no',
        'item_description', 'unit', 'quantity', 'unit_cost', 'total_cost',
        'remarks', 'status',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }
}
