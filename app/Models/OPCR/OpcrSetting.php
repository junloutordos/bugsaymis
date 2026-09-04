<?php

namespace App\Models\OPCR;

use Illuminate\Database\Eloquent\Model;

class OpcrSetting extends Model
{
    protected $fillable = [
        'campus_director_name',
        'oic_campus_director_name',
        'executive_director_name',
        'commitment_statement',
    ];

    /**
     * Singleton settings row, auto-created on first access.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create();
    }
}
