<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = ['version', 'date', 'remarks', 'changes', 'is_current', 'is_visible'];

    protected $casts = [
        'is_current' => 'boolean',
        'is_visible' => 'boolean',
        'date' => 'date:Y-m-d',
        'changes' => 'array',
    ];

    /**
     * Mark this version as current and unset all others.
     */
    public function makeCurrent(): void
    {
        static::query()->update(['is_current' => false]);
        $this->update(['is_current' => true]);
    }
}
