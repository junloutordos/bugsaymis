<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecognitionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nomination_id',
        'actor_id',
        'action',
        'notes',
        'status_snapshot',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(RewardNomination::class, 'nomination_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
