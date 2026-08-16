<?php

namespace App\Models\SPMS;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalPeriod extends Model
{
    use HasFactory;

    protected $table = 'spms_fiscal_periods';

    protected $fillable = [
        'cadence', 'fiscal_year', 'label', 'start_date', 'end_date',
        'parent_period_id', 'school_year_id', 'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'fiscal_year' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'parent_period_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class, 'parent_period_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeOfCadence(Builder $query, string $cadence): Builder
    {
        return $query->where('cadence', $cadence);
    }
}
