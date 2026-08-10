<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentContainmentIncident extends Model
{
    protected $fillable = [
        'device_id', 'reason', 'detail', 'triggered_at',
        'confirmed_by', 'confirmed_at', 'released_by', 'released_at',
    ];

    protected $casts = [
        'detail' => 'array',
        'triggered_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }

    public function isActive(): bool
    {
        return is_null($this->released_at);
    }
}
