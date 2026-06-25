<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtlasSentinelRelease extends Model
{
    protected $table = 'atlas_sentinel_releases';

    protected $fillable = [
        'version',
        's3_key',
        'sha256',
        'release_notes',
    ];

    public static function latestRelease(): ?self
    {
        return static::orderByDesc('id')->first();
    }
}
