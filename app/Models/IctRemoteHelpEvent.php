<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail for a remote-help request — every state change
 * (created, anydesk_id_reported, claimed, connected, ended) is logged with the
 * acting user so a remote session is fully accountable.
 */
class IctRemoteHelpEvent extends Model
{
    protected $table = 'ict_remote_help_events';

    // Single created_at column, set explicitly — no updated_at on an append-only log.
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'user_id',
        'event',
        'details',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->created_at ??= now();
        });
    }

    public function request()
    {
        return $this->belongsTo(IctRemoteHelpRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
