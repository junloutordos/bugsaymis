<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;

class BellScheduleSetting extends Model
{
    protected $table = 'bell_schedule_settings';

    protected $fillable = [
        'setting_key',
        'value',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
