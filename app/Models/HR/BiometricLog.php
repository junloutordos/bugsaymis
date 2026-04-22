<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricLog extends Model
{
    protected $table = 'biometric_logs';

    protected $fillable = [
        'device_employee_id',
        'user_id',
        'device_id',
        'log_datetime',
        'log_type',
        'source',
        'is_resolved',
        'is_duplicate',
        'import_batch',
    ];

    protected $casts = [
        'log_datetime'  => 'datetime',
        'imported_at'   => 'datetime',
        'is_resolved'   => 'boolean',
        'is_duplicate'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }
}
