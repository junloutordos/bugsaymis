<?php

namespace App\Models\StudentAttendance;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentContact extends Model
{
    protected $table = 'parent_contacts';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'mobile_phone',
        'fcm_device_token',
        'notify_email',
        'notify_push',
        'notify_sms',
    ];

    protected $casts = [
        'notify_email' => 'boolean',
        'notify_push'  => 'boolean',
        'notify_sms'   => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'student_parent_contact',
            'parent_contact_id',
            'student_id'
        )->withPivot('relationship');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function hasPushToken(): bool
    {
        return ! empty($this->fcm_device_token);
    }

    public function wantsEmailNotification(): bool
    {
        return $this->notify_email && ! empty($this->email);
    }

    public function wantsPushNotification(): bool
    {
        return $this->notify_push && $this->hasPushToken();
    }
}
