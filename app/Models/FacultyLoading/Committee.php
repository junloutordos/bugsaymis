<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps to the existing `committees` table.
 *
 * The core table was created in migration 2026_03_06_000002 with
 * name, head_id, and description columns. Migration 000012 adds
 * Faculty Loading fields: code, committee_type, load unit rates,
 * and is_active.
 */
class Committee extends Model
{
    protected $table = 'committees';

    protected $fillable = [
        'name',
        'code',
        'committee_type',
        'description',
        'head_id',
        'parent_committee_id',
        'max_members',
        'chairperson_title',
        'chairperson_load_units',
        'member_load_units',
        'is_active',
    ];

    protected $casts = [
        'max_members'            => 'integer',
        'chairperson_load_units' => 'decimal:2',
        'member_load_units'      => 'decimal:2',
        'is_active'              => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    /** Members via the existing committee_user pivot. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'committee_user')
                    ->withPivot('task');
    }

    /** Faculty Loading committee assignments (per-term records). */
    public function facultyAssignments(): HasMany
    {
        return $this->hasMany(FacultyCommitteeAssignment::class, 'committee_id');
    }

    /** WDP plans tagged to this committee (shared pivot with PMS). */
    public function workDistributionPlans(): BelongsToMany
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'committee_work_distribution_plan');
    }

    public function parentCommittee(): BelongsTo
    {
        return $this->belongsTo(Committee::class, 'parent_committee_id');
    }

    public function subCommittees(): HasMany
    {
        return $this->hasMany(Committee::class, 'parent_committee_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('committee_type', $type);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isSubCommittee(): bool
    {
        return $this->parent_committee_id !== null;
    }

    public function isMain(): bool
    {
        return $this->parent_committee_id === null
            && ($this->relationLoaded('subCommittees')
                ? $this->subCommittees->isNotEmpty()
                : $this->subCommittees()->exists());
    }

    public function isSimple(): bool
    {
        return $this->parent_committee_id === null
            && ($this->relationLoaded('subCommittees')
                ? $this->subCommittees->isEmpty()
                : ! $this->subCommittees()->exists());
    }

    /** Load units for a given role in this committee. */
    public function loadUnitsFor(string $role): float
    {
        return in_array($role, ['chairperson', 'co_chair'])
            ? (float) $this->chairperson_load_units
            : (float) $this->member_load_units;
    }
}
