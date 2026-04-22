<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    protected $fillable = [
        'nomination_id',
        'award_date',
        'incentive_type',
        'incentive_value',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'award_date'      => 'date',
        'incentive_value' => 'decimal:2',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(RewardNomination::class, 'nomination_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
