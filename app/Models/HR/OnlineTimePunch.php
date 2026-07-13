<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineTimePunch extends Model
{
    protected $table = 'online_time_punches';

    protected $appends = ['photo_url'];

    protected $fillable = [
        'user_id',
        'face_enrollment_id',
        'work_date',
        'punch_type',
        'punched_at',
        'photo_s3_key',
        'liveness_session_id',
        'liveness_confidence',
        'match_score',
        'match_status',
        'failure_reason',
        'ip_address',
        'latitude',
        'longitude',
        'geofence_status',
        'distance_meters',
        'network_status',
        'liveness_status',
        'liveness_challenge_type',
        'challenge_frames_s3_keys',
        'user_agent',
    ];

    protected $casts = [
        'work_date'                => 'date:Y-m-d',
        'punched_at'                => 'datetime',
        'liveness_confidence'       => 'decimal:2',
        'match_score'               => 'decimal:2',
        'latitude'                  => 'decimal:7',
        'longitude'                 => 'decimal:7',
        'distance_meters'           => 'decimal:2',
        'challenge_frames_s3_keys'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faceEnrollment(): BelongsTo
    {
        return $this->belongsTo(FaceEnrollment::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_s3_key) {
            return null;
        }

        $encoded = 's3.' . rtrim(strtr(base64_encode($this->photo_s3_key), '+/', '-_'), '=');

        return route('hr.online-punch.photo', ['fileId' => $encoded]);
    }
}
