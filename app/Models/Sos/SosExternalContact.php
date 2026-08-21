<?php

namespace App\Models\Sos;

use Illuminate\Database\Eloquent\Model;

class SosExternalContact extends Model
{
    protected $fillable = ['name', 'org', 'phone', 'email', 'alert_types', 'channel', 'active'];

    protected $casts = ['alert_types' => 'array', 'active' => 'boolean'];
}
