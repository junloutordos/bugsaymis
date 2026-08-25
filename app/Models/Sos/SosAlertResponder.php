<?php

namespace App\Models\Sos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlertResponder extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['sos_alert_id', 'user_id', 'claimed_at', 'unclaimed_at'];

    protected $casts = ['claimed_at' => 'datetime', 'unclaimed_at' => 'datetime'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
