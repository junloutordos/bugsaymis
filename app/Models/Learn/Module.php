<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $table = 'learn_modules';

    protected $fillable = ['learn_course_id', 'title', 'position', 'published_at'];

    protected $casts = [
        'position' => 'integer',
        'published_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'learn_course_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ModuleItem::class, 'learn_module_id')->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
