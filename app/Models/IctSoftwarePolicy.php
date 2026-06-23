<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctSoftwarePolicy extends Model
{
    protected $table = 'ict_software_policy';

    protected $fillable = [
        'name_pattern',
        'policy',
        'notes',
    ];
}
