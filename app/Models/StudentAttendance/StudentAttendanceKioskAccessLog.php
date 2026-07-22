<?php

namespace App\Models\StudentAttendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendanceKioskAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'kiosk_device_id',
        'user_id',
        'event',
        'reason',
        'ip_hash',
        'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    public function kioskDevice(): BelongsTo
    {
        return $this->belongsTo(StudentAttendanceDevice::class, 'kiosk_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
