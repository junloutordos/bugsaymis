<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;

class SalarySchedule extends Model
{
    protected $table = 'salary_schedules';

    protected $fillable = [
        'salary_grade',
        'step',
        'monthly_rate',
        'annual_rate',
        'effective_date',
        'is_current',
        'schedule_name',
        'remarks',
    ];

    protected $casts = [
        'salary_grade'   => 'integer',
        'step'           => 'integer',
        'monthly_rate'   => 'decimal:2',
        'annual_rate'    => 'decimal:2',
        'effective_date' => 'date:Y-m-d',
        'is_current'     => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeForGrade($query, int $grade)
    {
        return $query->where('salary_grade', $grade);
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Return the current annual rate for a given salary grade and step.
     * Returns null if no current schedule entry is found.
     */
    public static function lookupAnnualRate(int $salaryGrade, int $step = 1): ?float
    {
        $row = static::where('salary_grade', $salaryGrade)
            ->where('step', $step)
            ->where('is_current', true)
            ->first();

        return $row ? (float) $row->annual_rate : null;
    }

    /**
     * Return all step rates for a given salary grade (current schedule only).
     * Keyed by step number.
     */
    public static function ratesForGrade(int $salaryGrade): array
    {
        return static::where('salary_grade', $salaryGrade)
            ->where('is_current', true)
            ->orderBy('step')
            ->get(['step', 'monthly_rate', 'annual_rate'])
            ->keyBy('step')
            ->map(fn ($r) => [
                'monthly_rate' => (float) $r->monthly_rate,
                'annual_rate'  => (float) $r->annual_rate,
            ])
            ->toArray();
    }

    /**
     * Mark all existing rows for a given schedule_name as not current,
     * then set the new batch as current.  Used when activating a new SSL.
     */
    public static function activateSchedule(string $scheduleName): int
    {
        static::where('is_current', true)->update(['is_current' => false]);
        return static::where('schedule_name', $scheduleName)
            ->update(['is_current' => true]);
    }
}
