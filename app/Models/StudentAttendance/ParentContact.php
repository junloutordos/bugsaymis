<?php

namespace App\Models\StudentAttendance;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ParentContact extends Model
{
    // AtlasGo authenticates parents directly against this model and issues
    // Sanctum tokens against it — parents no longer need a row in the main
    // Atlas `users` table. `user_id` is kept only for legacy/front-desk rows.
    use HasApiTokens, Notifiable;

    protected $table = 'parent_contacts';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'password',
        'status',
        'email_verified_at',
        'mobile_phone',
        'fcm_device_token',
        'notify_email',
        'notify_push',
        'notify_sms',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'notify_email'      => 'boolean',
        'notify_push'       => 'boolean',
        'notify_sms'        => 'boolean',
        'password'          => 'hashed',
        'email_verified_at' => 'datetime',
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
