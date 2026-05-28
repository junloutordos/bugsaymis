<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IssuanceType extends Model
{
    protected $primaryKey  = 'code';
    protected $keyType     = 'string';
    public    $incrementing = false;

    protected $fillable = ['code', 'label', 'description', 'series_padding', 'is_active'];

    protected $casts = [
        'is_active'      => 'boolean',
        'series_padding' => 'integer',
    ];

    public function seriesSettings()
    {
        return $this->hasMany(IssuanceSeriesSetting::class, 'type_code', 'code');
    }

    public static function activeLabels(): array
    {
        return Cache::remember('issuance_types_active', 300, function () {
            return static::where('is_active', true)
                ->orderBy('label')
                ->pluck('label', 'code')
                ->toArray();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('issuance_types_active');
    }
}
