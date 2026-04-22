<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;

class SstPosition extends Model
{
    protected $table = 'sst_positions';

    protected $fillable = [
        'code',
        'name',
        'salary_grade',
        'full_load_threshold',
        'min_teaching_units',
        'max_admin_units',
        'max_research_units',
        'overload_threshold',
        'max_overload_units',
        'description',
        'is_active',
    ];

    protected $casts = [
        'salary_grade'        => 'integer',
        'full_load_threshold' => 'decimal:2',
        'min_teaching_units'  => 'decimal:2',
        'max_admin_units'     => 'decimal:2',
        'max_research_units'  => 'decimal:2',
        'overload_threshold'  => 'decimal:2',
        'max_overload_units'  => 'decimal:2',
        'is_active'           => 'boolean',
    ];

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Find by position code, e.g., SstPosition::forCode('SST-III') */
    public static function forCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isOverloaded(float $totalUnits): bool
    {
        return $totalUnits > $this->overload_threshold;
    }

    public function isUnderloaded(float $totalUnits): bool
    {
        return $totalUnits < $this->full_load_threshold;
    }

    public function overloadUnits(float $totalUnits): float
    {
        return max(0, $totalUnits - $this->overload_threshold);
    }
}
