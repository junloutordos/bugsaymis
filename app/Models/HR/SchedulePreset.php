<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class SchedulePreset extends Model
{
    protected $table = 'schedule_presets';

    protected $fillable = [
        'name',
        'schedule_type',
        'daily_schedules',
        'grace_period_minutes',
        'late_threshold_minutes',
        'half_day_hours',
        'remarks',
    ];

    protected $casts = [
        'daily_schedules'        => 'array',
        'grace_period_minutes'   => 'integer',
        'late_threshold_minutes' => 'integer',
        'half_day_hours'         => 'integer',
    ];

    /** Return the active work days (keys of daily_schedules). */
    public function getWorkDays(): array
    {
        return array_keys($this->daily_schedules ?? []);
    }

    /** Return time_in for a given day abbreviation (Mon, Tue, …), or null. */
    public function getTimeIn(string $day): ?string
    {
        return $this->daily_schedules[$day]['time_in'] ?? null;
    }

    /** Return time_out for a given day abbreviation. */
    public function getTimeOut(string $day): ?string
    {
        return $this->daily_schedules[$day]['time_out'] ?? null;
    }
}
