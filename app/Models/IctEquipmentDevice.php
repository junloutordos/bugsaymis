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
        'last_update_attempted_at',
        'last_update_result',
        'last_update_details',
        'health_score',
        'risk_score',
        'risk_tier',
        'last_full_inventory_at',
        'last_diagnostics_at',
    ];

    protected $casts = [
        'last_checkin_at' => 'datetime',
        'network_location_changed_at' => 'datetime',
        'last_update_attempted_at' => 'datetime',
        'last_full_inventory_at' => 'datetime',
        'last_diagnostics_at' => 'datetime',
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

    public function healthHistory()
    {
        return $this->hasMany(IctEquipmentHealthHistory::class, 'device_id');
    }

    public function hardwareInventory()
    {
        return $this->hasOne(IctEquipmentHardwareInventory::class, 'device_id');
    }

    public function softwareInventory()
    {
        return $this->hasOne(IctEquipmentSoftwareInventory::class, 'device_id');
    }

    public function securityStatus()
    {
        return $this->hasOne(IctEquipmentSecurityStatus::class, 'device_id');
    }

    public function remediationLog()
    {
        return $this->hasMany(IctEquipmentRemediationLog::class, 'device_id');
    }

    public function manualRemediationRequests()
    {
        return $this->hasMany(IctEquipmentManualRemediationRequest::class, 'device_id');
    }
}
