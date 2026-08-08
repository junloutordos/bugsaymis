<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModuleItem extends Model
{
    protected $table = 'learn_module_items';

    protected $fillable = ['learn_module_id', 'itemable_type', 'itemable_id', 'position', 'published_at'];

    protected $casts = [
        'position' => 'integer',
        'published_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'learn_module_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
