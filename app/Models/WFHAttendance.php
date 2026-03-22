<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WFHAttendance extends Model
{
    protected $table = 'wfh_attendances';

    protected $fillable = [
        'user_id',
        'date',
        'time_in',
        'time_in_photo_file_id',
        'time_in_photo_link',
        'break_out',
        'break_in',
        'time_out',
        'time_out_photo_file_id',
        'time_out_photo_link',
        'ip_address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'date'      => 'date',
        'time_in'   => 'datetime',
        'break_out' => 'datetime',
        'break_in'  => 'datetime',
        'time_out'  => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accomplishments()
    {
        return $this->hasMany(WFHAccomplishment::class, 'wfh_attendance_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isTimedOut(): bool
    {
        return $this->time_out !== null;
    }

    public function isTimedIn(): bool
    {
        return $this->time_in !== null;
    }
}
