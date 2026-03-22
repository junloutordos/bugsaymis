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
        'time_in',
        'time_out',
        'grace_period_minutes',
        'late_threshold_minutes',
        'half_day_hours',
        'effective_date',
        'end_date',
        'is_default',
        'remarks',
    ];

    protected $casts = [
        'work_days'              => 'array',
        'effective_date'         => 'date',
        'end_date'               => 'date',
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
     */
    public function scopeActiveOnDate($query, string $date)
    {
        return $query
            ->where('effective_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date));
    }

    /**
     * Get the active work days as 3-letter abbreviations (Mon, Tue, …).
     * Defaults to Mon–Fri if not set.
     */
    public function getWorkDaysArray(): array
    {
        return $this->work_days ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    }

    /**
     * Is the given date a scheduled work day?
     */
    public function isWorkDay(string $date): bool
    {
        $dow = \Carbon\Carbon::parse($date)->format('D'); // Mon, Tue, …
        return in_array($dow, $this->getWorkDaysArray());
    }
}
