<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabEquipmentRequestItem extends Model
{
    protected $table = 'lab_equipment_request_items';

    protected $fillable = [
        'lab_equipment_request_id', 'lab_equipment_id', 'quantity', 'item', 'description', 'issued_condition', 'returned_condition', 'needs_repair', 'needs_replacement', 'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'needs_repair' => 'boolean',
        'needs_replacement' => 'boolean',
    ];

    public function request() { return $this->belongsTo(LabEquipmentRequest::class, 'lab_equipment_request_id'); }

    public function equipment() { return $this->belongsTo(LabEquipment::class, 'lab_equipment_id'); }
}
