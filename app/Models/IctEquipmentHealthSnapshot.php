<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentHealthSnapshot extends Model
{
    protected $table = 'ict_equipment_health_snapshots';

    protected $fillable = [
        'device_id',
        'payload',
        'recorded_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
