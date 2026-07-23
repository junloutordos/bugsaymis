<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricDevice extends Model
{
    protected $table = 'biometric_devices';

    protected $fillable = [
        'ict_equipment_device_id',
        'device_key',
        'label',
        'receiver_port',
        'is_active',
        'last_relay_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_relay_at' => 'datetime',
    ];

    public function bridgeEquipment(): BelongsTo
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'ict_equipment_device_id');
    }
}
