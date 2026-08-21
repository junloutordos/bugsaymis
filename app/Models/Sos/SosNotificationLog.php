<?php

namespace App\Models\Sos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosNotificationLog extends Model
{
    protected $fillable = [
        'sos_alert_id', 'channel', 'recipient_type', 'recipient_id',
        'recipient_label', 'sent', 'sent_at',
    ];

    protected $casts = ['sent' => 'boolean', 'sent_at' => 'datetime'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }
}
