<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentHealthHistory extends Model
{
    protected $table = 'ict_equipment_health_history';

    protected $fillable = [
        'device_id',
        'health_score',
        'risk_score',
        'payload',
        'recorded_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
