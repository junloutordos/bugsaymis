<?php

namespace App\Models;

use App\Services\OPCR\OpcrIndicatorPropagationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceIndicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_outcome_id',
        'description',
        'target',
        'budget',
        'fiscal_year',
    ];

    // Keeps OPCR in sync regardless of how a Performance Indicator is
    // created/updated/deleted — the HTTP controller, the annual Copy
    // Framework rollover (IPCRRatingPeriodController::copyFramework), tinker,
    // future imports, all go through save()/delete() and are covered here.
    protected static function booted(): void
    {
        static::saved(function (PerformanceIndicator $indicator) {
            app(OpcrIndicatorPropagationService::class)->syncFromPerformanceIndicator($indicator);
        });

        static::deleted(function (PerformanceIndicator $indicator) {
            app(OpcrIndicatorPropagationService::class)->unlinkFromPerformanceIndicator($indicator);
        });
    }

    // Divisions are synced via a separate pivot call after save() (by every
    // caller, not just this model), so the `saved` hook above sees an empty
    // divisions list at creation time. Route division assignment through
    // this method instead of calling divisions()->sync() directly, so the
    // OPCR side re-syncs once divisions actually reflect reality.
    public function syncDivisions(array $divisionIds): void
    {
        $this->divisions()->sync($divisionIds);
        app(OpcrIndicatorPropagationService::class)->syncFromPerformanceIndicator($this->fresh('divisions'));
    }

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

    public function agencyOutcome()
    {
        return $this->belongsTo(AgencyOutcome::class, 'agency_outcome_id','id');
    }

     // ✅ Use only many-to-many
    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'division_performance_indicator');
    }

}
