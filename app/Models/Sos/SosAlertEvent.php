<?php

namespace App\Models\Sos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlertEvent extends Model
{
    const UPDATED_AT = null;

    // 'created_at' is intentionally fillable: this append-only timeline is
    // only ever written by trusted service/cron code (never raw user input),
    // and the escalation sweep's own tests need to backdate events to
    // simulate an alert that has sat in a tier past its timeout.
    protected $fillable = ['sos_alert_id', 'type', 'actor_type', 'actor_id', 'payload', 'created_at'];

    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }
}
