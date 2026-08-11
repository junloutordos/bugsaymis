<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuanceRecipientCriterion extends Model
{
    protected $fillable = ['issuance_id', 'type', 'target_id'];

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(Issuance::class);
    }
}
