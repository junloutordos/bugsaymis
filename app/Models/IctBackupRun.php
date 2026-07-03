<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctBackupRun extends Model
{
    protected $table = 'ict_backup_runs';

    protected $fillable = [
        'device_id',
        'schedule_id',
        'status',
        'config',
        'files_scanned',
        'files_uploaded',
        'files_skipped',
        'bytes_uploaded',
        'errors',
        'dispatched_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'config' => 'array',
        'errors' => 'array',
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
