<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceEnrollment extends Model
{
    protected $table = 'face_enrollments';

    protected $fillable = [
        'user_id',
        's3_key',
        'status',
        'consent_given_at',
        'consent_ip',
        'enrolled_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'is_active',
    ];

    protected $casts = [
        'consent_given_at' => 'datetime',
        'enrolled_at'       => 'datetime',
        'approved_at'       => 'datetime',
        'is_active'         => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
