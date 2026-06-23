<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctAgentRelease extends Model
{
    protected $table = 'ict_agent_releases';

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
