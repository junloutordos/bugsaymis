<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentHardwareInventoryChange extends Model
{
    protected $table = 'ict_equipment_hardware_inventory_changes';

    protected $fillable = [
        'device_id',
        'field',
        'old_value',
        'new_value',
        'detected_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
