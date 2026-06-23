<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentSecurityStatus extends Model
{
    protected $table = 'ict_equipment_security_status';

    protected $fillable = [
        'device_id',
        'antivirus_enabled',
        'antivirus_up_to_date',
        'firewall_enabled',
        'pending_updates_count',
        'last_update_installed_at',
        'reboot_required',
        'unauthorized_software_count',
        'security_score',
        'recorded_at',
    ];

    protected $casts = [
        'antivirus_enabled' => 'boolean',
        'antivirus_up_to_date' => 'boolean',
        'firewall_enabled' => 'boolean',
        'reboot_required' => 'boolean',
        'last_update_installed_at' => 'datetime',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
