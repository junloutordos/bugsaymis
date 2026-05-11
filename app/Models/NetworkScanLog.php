<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkScanLog extends Model
{
    protected $table = 'network_scan_logs';

    protected $fillable = [
        'source',
        'scanned_at',
        'duration_ms',
        'devices_found',
        'devices_online',
        'status',
        'error_message',
        'agent_version',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];
}
