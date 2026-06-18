<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentEnrollmentToken extends Model
{
    protected $table = 'ict_equipment_enrollment_tokens';

    protected $fillable = [
        'token',
        'created_by',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
