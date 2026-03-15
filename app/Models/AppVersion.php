<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = ['version', 'date', 'remarks', 'is_current'];

    protected $casts = [
        'is_current' => 'boolean',
        'date'       => 'date:Y-m-d',
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
