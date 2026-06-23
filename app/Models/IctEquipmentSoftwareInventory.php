<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentSoftwareInventory extends Model
{
    protected $table = 'ict_equipment_software_inventory';

    protected $fillable = [
        'device_id',
        'installed_software',
        'recorded_at',
    ];

    protected $casts = [
        'installed_software' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
