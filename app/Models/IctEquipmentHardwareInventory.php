<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentHardwareInventory extends Model
{
    protected $table = 'ict_equipment_hardware_inventory';

    protected $fillable = [
        'device_id',
        'cpu_model',
        'cpu_cores',
        'ram_modules',
        'disks',
        'gpu',
        'peripherals',
        'battery',
        'recorded_at',
    ];

    protected $casts = [
        'ram_modules' => 'array',
        'disks' => 'array',
        'gpu' => 'array',
        'peripherals' => 'array',
        'battery' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
