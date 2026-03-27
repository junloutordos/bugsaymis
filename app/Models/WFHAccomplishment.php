<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WFHAccomplishment extends Model
{
    protected $table = 'wfh_accomplishments';

    protected $fillable = [
        'wfh_attendance_id',
        'user_id',
        'title',
        'description',
        'time_from',
        'time_to',
        'proof_type',
        'proof_link',
        'google_drive_file_id',
        'google_drive_link',
        'file_name',
    ];

    protected $casts = [
        'proof_type' => 'string',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function attendance()
    {
        return $this->belongsTo(WFHAttendance::class, 'wfh_attendance_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function hasPhoto(): bool
    {
        return $this->proof_type === 'photo' && $this->google_drive_link !== null;
    }

    public function hasLink(): bool
    {
        return $this->proof_type === 'link' && $this->proof_link !== null;
    }
}
