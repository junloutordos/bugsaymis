<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentAlert extends Model
{
    protected $table = 'ict_equipment_alerts';

    protected $fillable = [
        'device_id',
        'equipment_id',
        'issue',
        'severity',
        'status',
        'it_job_request_id',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }

    public function equipment()
    {
        return $this->belongsTo(ICTEquipment::class, 'equipment_id');
    }

    public function jobRequest()
    {
        return $this->belongsTo(ITJobRequest::class, 'it_job_request_id');
    }
}
