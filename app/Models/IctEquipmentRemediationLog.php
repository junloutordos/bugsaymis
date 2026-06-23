<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentRemediationLog extends Model
{
    protected $table = 'ict_equipment_remediation_log';

    protected $fillable = [
        'device_id',
        'action',
        'target',
        'trigger_code',
        'result',
        'details',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
