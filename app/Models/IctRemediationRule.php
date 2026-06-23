<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctRemediationRule extends Model
{
    protected $table = 'ict_remediation_rules';

    protected $fillable = [
        'metric_key',
        'condition',
        'action',
        'auto_execute',
        'cooldown_minutes',
        'enabled',
    ];

    protected $casts = [
        'auto_execute' => 'boolean',
        'enabled' => 'boolean',
    ];
}
