<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctBackupFolder extends Model
{
    protected $table = 'ict_backup_folders';

    protected $fillable = [
        'device_id',
        'path_hash',
        'path',
        'drive_folder_id',
    ];
}
