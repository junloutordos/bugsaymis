<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherTapLog extends Model
{
    protected $table = 'teacher_tap_logs';

    protected $fillable = [
        'user_id',
        'classroom_id',
        'class_schedule_id',
        'class_schedule_day_adjustment_id',
        'tapped_at',
        'status',
        'is_late',
        'late_minutes',
        'channel',
        'ip_address',
        'location_status',
        'network_status',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'tapped_at'    => 'datetime',
        'is_late'      => 'boolean',
        'late_minutes' => 'integer',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function dayAdjustment(): BelongsTo
    {
        return $this->belongsTo(ClassScheduleDayAdjustment::class, 'class_schedule_day_adjustment_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeToday($query)
    {
        return $query->whereDate('tapped_at', today());
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('tapped_at', $date);
    }
}
