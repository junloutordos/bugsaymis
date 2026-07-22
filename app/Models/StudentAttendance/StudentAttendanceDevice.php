<?php

namespace App\Models\StudentAttendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAttendanceDevice extends Model
{
    protected $fillable = [
        'name',
        'gate_location',
        'token_hash',
        'is_active',
        'paired_by',
        'last_seen_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function pairedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paired_by');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(StudentAttendanceLog::class, 'kiosk_device_id');
    }

    public static function resolveToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();
    }
}
