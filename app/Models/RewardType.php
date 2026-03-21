<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'category',
        'type',
        'criteria',
        'frequency',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function nominations(): HasMany
    {
        return $this->hasMany(RewardNomination::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
