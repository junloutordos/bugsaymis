<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;

class SchoolDayConfig extends Model
{
    protected $table = 'school_day_configs';

    protected $fillable = [
        'day_of_week',
        'classes_start',
        'classes_end',
        'blocked_periods',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'blocked_periods' => 'array',
        'is_active'       => 'boolean',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
