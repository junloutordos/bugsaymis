<?php

namespace App\Models\Atlas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtlasModuleMaturityScore extends Model
{
    protected $fillable = [
        'module_key',
        'dimension',
        'score',
        'criteria_checks',
        'notes',
        'scored_by',
        'scored_at',
    ];

    protected $casts = [
        'score'           => 'integer',
        'criteria_checks' => 'array',
        'scored_at'       => 'datetime',
    ];

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
