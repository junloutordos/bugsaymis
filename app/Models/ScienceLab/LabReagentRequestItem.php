<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabReagentRequestItem extends Model
{
    protected $table = 'lab_reagent_request_items';

    protected $fillable = [
        'lab_reagent_request_id', 'lab_consumable_id', 'quantity', 'reagent', 'sds_read', 'issued_amount', 'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'sds_read' => 'boolean',
    ];

    public function request() { return $this->belongsTo(LabReagentRequest::class, 'lab_reagent_request_id'); }

    public function consumable() { return $this->belongsTo(LabConsumable::class, 'lab_consumable_id'); }
}
