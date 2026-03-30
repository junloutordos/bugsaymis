<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryGrade extends Model
{
    protected $fillable = [
        'grade',
        'step',
        'monthly_rate',
        'tranche',
        'effective_date',
        'is_current',
    ];

    protected $casts = [
        'monthly_rate'   => 'decimal:2',
        'effective_date' => 'date',
        'is_current'     => 'boolean',
        'grade'          => 'integer',
        'step'           => 'integer',
    ];

    /**
     * Get the monthly rate for a specific grade and step from the current tranche.
     */
    public static function rateFor(int $grade, int $step = 1): ?string
    {
        return static::where('grade', $grade)
            ->where('step', $step)
            ->where('is_current', true)
            ->value('monthly_rate');
    }

    /**
     * Return all grades (1-33) with steps (1-8) keyed as grade => [step => rate].
     */
    public static function table(): array
    {
        $rows = static::where('is_current', true)->orderBy('grade')->orderBy('step')->get();
        $table = [];
        foreach ($rows as $row) {
            $table[$row->grade][$row->step] = $row->monthly_rate;
        }
        return $table;
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
