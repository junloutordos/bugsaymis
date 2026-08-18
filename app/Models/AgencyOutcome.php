<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgencyOutcome extends Model
{
    use HasFactory;

    protected $table = 'agency_org_outcomes';

    protected $fillable = [
        'outcome',
        'sub_outcome',
        'function_type', // ✅ new field
        'fiscal_year',
        'parent_id',
    ];

    // NULL fiscal_year = applies to all years (legacy rows)
    public function scopeForFiscalYear($query, ?int $year)
    {
        if (! $year) {
            return $query;
        }

        return $query->where(function ($q) use ($year) {
            $q->whereNull('fiscal_year')->orWhere('fiscal_year', $year);
        });
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function performanceIndicators()
    {
        return $this->hasMany(PerformanceIndicator::class, 'agency_outcome_id');
    }
}
