<?php

namespace App\Models\PPMP;

use Illuminate\Database\Eloquent\Model;

class PpmpSetting extends Model
{
    protected $table = 'ppmp_settings';

    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, with an optional default.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value (create or update).
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
