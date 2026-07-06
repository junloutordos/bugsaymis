<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user-initiated "Ask for Remote Help" request from the Atlas Sentinel tray.
 * Drives an attended AnyDesk session: the tray reports the device's AnyDesk ID,
 * MIS connects to it, and the user clicks Accept on the incoming connection.
 * The AnyDesk ID is an address (not a secret) and there is no password to
 * rotate — every session is authorized by the user in person.
 */
class IctRemoteHelpRequest extends Model
{
    protected $table = 'ict_remote_help_requests';

    // Statuses
    public const STATUS_WAITING    = 'waiting';
    public const STATUS_CLAIMED    = 'claimed';
    public const STATUS_IN_SESSION = 'in_session';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_CANCELED   = 'canceled';
    public const STATUS_TIMED_OUT  = 'timed_out';
    public const STATUS_FAILED     = 'failed';

    /** Statuses where the request is still live / connectable. */
    public const ACTIVE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_CLAIMED,
        self::STATUS_IN_SESSION,
    ];

    protected $fillable = [
        'device_id',
        'equipment_id',
        'note',
        'status',
        'it_job_request_id',
        'claimed_by',
        'claimed_at',
        'anydesk_id',
        'session_ready_at',
        'revealed_at',
        'ended_at',
        'end_reason',
        'error_message',
    ];

    protected $casts = [
        'claimed_at'       => 'datetime',
        'session_ready_at' => 'datetime',
        'revealed_at'      => 'datetime',
        'ended_at'         => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }

    public function equipment()
    {
        return $this->belongsTo(ICTEquipment::class, 'equipment_id');
    }

    public function claimer()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function jobRequest()
    {
        return $this->belongsTo(ITJobRequest::class, 'it_job_request_id');
    }

    public function events()
    {
        return $this->hasMany(IctRemoteHelpEvent::class, 'request_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
