<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OedIssuanceCategory extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'label', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function issuances()
    {
        return $this->hasMany(OedIssuance::class, 'category_code', 'code');
    }

    public static function activeLabels(): array
    {
        return static::where('is_active', true)
            ->orderBy('label')
            ->pluck('label', 'code')
            ->toArray();
    }
}
