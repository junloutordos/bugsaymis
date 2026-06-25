<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentEnrollmentToken extends Model
{
    protected $table = 'ict_equipment_enrollment_tokens';

    protected $fillable = [
        'token',
        'label',
        'created_by',
        'expires_at',
        'max_uses',
        'uses_count',
        'used_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        if ($this->revoked_at || $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->uses_count < $this->max_uses;
    }
}
