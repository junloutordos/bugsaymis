<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssuanceSeriesSetting extends Model
{
    protected $fillable = ['type_code', 'year', 'series_start'];

    protected $casts = [
        'year'         => 'integer',
        'series_start' => 'integer',
    ];

    public function type()
    {
        return $this->belongsTo(IssuanceType::class, 'type_code', 'code');
    }
}
