<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\WorkDistributionPlan;

class DesignationCategory extends Model
{
    protected $table = 'designation_categories';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    /**
     * Work Distribution Plans tagged directly on this category. Every
     * designation under this category — and therefore every current and
     * future faculty member holding any of them (via
     * LoadAssignment::designation_id) — inherits these same plans on their
     * IPCR automatically, set once on the "mother" category record instead
     * of per individual designation.
     */
    public function workDistributionPlans(): BelongsToMany
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'designation_category_work_distribution_plan')
            ->withTimestamps();
    }

    public function activeDesignations(): HasMany
    {
        return $this->hasMany(Designation::class)->where('is_active', true);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
