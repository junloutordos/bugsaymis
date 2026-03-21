<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardApproval extends Model
{
    protected $fillable = [
        'nomination_id',
        'approver_id',
        'decision',
        'remarks',
        'level',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(RewardNomination::class, 'nomination_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
