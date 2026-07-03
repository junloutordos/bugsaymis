<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctBackupSchedule extends Model
{
    protected $table = 'ict_backup_schedules';

    protected $fillable = [
        'device_id',
        'enabled',
        'frequency',
        'weekly_day',
        'preferred_hour',
        'include_downloads',
        'max_file_mb',
        'last_dispatched_at',
        'run_requested_at',
        'created_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'include_downloads' => 'boolean',
        'last_dispatched_at' => 'datetime',
        'run_requested_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }

    public function runs()
    {
        return $this->hasMany(IctBackupRun::class, 'schedule_id');
    }
}
