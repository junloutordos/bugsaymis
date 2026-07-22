<?php

namespace App\Models\StudentAttendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendanceKioskOperator extends Model
{
    protected $fillable = [
        'user_id',
        'pin_hash',
        'is_active',
        'set_by',
        'pin_changed_at',
    ];

    protected $hidden = ['pin_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'pin_changed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
