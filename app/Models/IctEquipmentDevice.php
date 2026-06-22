<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class IctEquipmentDevice extends Model
{
    use HasApiTokens;

    protected $table = 'ict_equipment_devices';

    protected $fillable = [
        'equipment_id',
        'hostname',
        'mac_address',
        'os_version',
        'agent_version',
        'last_checkin_at',
        'network_location',
        'network_location_changed_at',
    ];

    protected $casts = [
        'last_checkin_at' => 'datetime',
        'network_location_changed_at' => 'datetime',
    ];

    public function equipment()
    {
        return $this->belongsTo(ICTEquipment::class, 'equipment_id');
    }

    public function healthSnapshot()
    {
        return $this->hasOne(IctEquipmentHealthSnapshot::class, 'device_id');
    }

    public function alerts()
    {
        return $this->hasMany(IctEquipmentAlert::class, 'device_id');
    }
}
