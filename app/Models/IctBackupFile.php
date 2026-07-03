<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctBackupFile extends Model
{
    protected $table = 'ict_backup_files';

    protected $fillable = [
        'device_id',
        'path_hash',
        'relative_path',
        'size_bytes',
        'content_sha256',
        'file_mtime',
        'drive_file_id',
        'last_backed_up_at',
    ];

    protected $casts = [
        'last_backed_up_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }
}
