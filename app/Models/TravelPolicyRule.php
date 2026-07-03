<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelPolicyRule extends Model
{
    protected $fillable = [
        'source',
        'rule_key',
        'label',
        'value',
        'source_url',
        'effective_date',
        'is_active',
    ];

    protected $casts = [
        'value' => 'array',
        'effective_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];
}
