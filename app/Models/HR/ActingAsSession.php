<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActingAsSession extends Model
{
    protected $table = 'acting_as_sessions';

    protected $fillable = [
        'substitution_id',
        'started_at',
        'ended_at',
        'ended_reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function substitution(): BelongsTo
    {
        return $this->belongsTo(Substitution::class);
    }

    /** Sessions that have not yet been closed. */
    public function scopeOpen($query)
    {
        return $query->whereNull('ended_at');
    }
}
