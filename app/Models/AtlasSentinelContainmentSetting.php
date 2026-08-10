<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtlasSentinelContainmentSetting extends Model
{
    protected $fillable = [
        'auto_contain_enabled', 'auto_release_minutes',
        'max_half_open_connections', 'max_distinct_ips_per_minute',
    ];

    protected $casts = [
        'auto_contain_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'auto_contain_enabled' => false,
            'auto_release_minutes' => 30,
            'max_half_open_connections' => 100,
            'max_distinct_ips_per_minute' => 50,
        ]);
    }
}
