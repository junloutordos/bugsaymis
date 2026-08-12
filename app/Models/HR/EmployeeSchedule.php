<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSchedule extends Model
{
    protected $table = 'employee_schedules';

    protected $fillable = [
        'user_id',
        'name',
        'schedule_type',
        'work_days',
        'daily_schedules',
        'time_in',
        'time_out',
        'grace_period_minutes',
        'late_threshold_minutes',
        'half_day_hours',
        'effective_date',
        'end_date',
        'is_default',
        'status',
        'rejection_reason',
        'remarks',
    ];

    protected $casts = [
        'work_days'              => 'array',
        'daily_schedules'        => 'array',
        'effective_date'         => 'date:Y-m-d',
        'end_date'               => 'date:Y-m-d',
        'is_default'             => 'boolean',
        'grace_period_minutes'   => 'integer',
        'late_threshold_minutes' => 'integer',
        'half_day_hours'         => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dtrRecords(): HasMany
    {
        return $this->hasMany(DtrRecord::class, 'schedule_id');
    }

    /**
     * Scope: schedules active on a given date.
     *
     * Only ever resolves an *approved* schedule — a pending submission
     * (awaiting HR review) or a rejected one must never be used to compute
     * official time-in/time-out, even if its effective_date is later than
     * the currently-approved schedule's. See GuardDirectoryService for the
     * original correct pattern this scope now centralizes.
     */
    public function scopeActiveOnDate($query, string $date)
    {
        return $query
            ->where('status', 'approved')
            ->where('effective_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date));
    }

    /**
     * Get the active work days as 3-letter abbreviations (Mon, Tue, …).
     * If daily_schedules is set, work days are its keys; otherwise fall back to work_days column.
     */
    public function getWorkDaysArray(): array
    {
        if (! empty($this->daily_schedules)) {
            return array_keys($this->daily_schedules);
        }
        return $this->work_days ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    }

    /**
     * Is the given date a scheduled work day?
     */
    public function isWorkDay(string $date): bool
    {
        $dow = \Carbon\Carbon::parse($date)->format('D');
        return in_array($dow, $this->getWorkDaysArray());
    }

    /**
     * Get the scheduled time_in for a specific date.
     * Checks daily_schedules first, then falls back to the time_in column.
     */
    public function getTimeIn(string $date): ?string
    {
        $dow = \Carbon\Carbon::parse($date)->format('D');
        return $this->daily_schedules[$dow]['time_in']
            ?? ($this->time_in ? (string) $this->time_in : null);
    }

    /**
     * Get the scheduled time_out for a specific date.
     */
    public function getTimeOut(string $date): ?string
    {
        $dow = \Carbon\Carbon::parse($date)->format('D');
        return $this->daily_schedules[$dow]['time_out']
            ?? ($this->time_out ? (string) $this->time_out : null);
    }

    /**
     * Get the scheduled lunch_start for a specific date, or null if not configured.
     */
    public function getLunchStart(string $date): ?string
    {
        $dow = \Carbon\Carbon::parse($date)->format('D');
        return $this->daily_schedules[$dow]['lunch_start'] ?? null;
    }

    /**
     * Get the scheduled lunch_end for a specific date, or null if not configured.
     */
    public function getLunchEnd(string $date): ?string
    {
        $dow = \Carbon\Carbon::parse($date)->format('D');
        return $this->daily_schedules[$dow]['lunch_end'] ?? null;
    }

    /**
     * Returns true if the schedule for the given date crosses midnight
     * (i.e. time_out < time_in, e.g. 22:00 → 06:00).
     */
    public function isOvernightShift(string $date): bool
    {
        $timeIn  = $this->getTimeIn($date);
        $timeOut = $this->getTimeOut($date);
        if (! $timeIn || ! $timeOut) {
            return false;
        }
        return $this->timeStrToMinutes(substr($timeIn, 0, 5))
             > $this->timeStrToMinutes(substr($timeOut, 0, 5));
    }

    private function timeStrToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }
}
