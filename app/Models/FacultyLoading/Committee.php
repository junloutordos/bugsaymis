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
        'scope_type',
        'school_year_id',
        'season_starts_at',
        'season_ends_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'amended_from_committee_id',
        'so_number',
        'issuance_id',
    ];

    protected $casts = [
        'max_members'            => 'integer',
        'chairperson_load_units' => 'decimal:2',
        'member_load_units'      => 'decimal:2',
        'is_active'              => 'boolean',
        'season_starts_at'       => 'date',
        'season_ends_at'         => 'date',
        'revoked_at'             => 'datetime',
    ];

    public const SCOPE_PERPETUAL   = 'perpetual';
    public const SCOPE_SCHOOL_YEAR = 'school_year';
    public const SCOPE_SEASONAL    = 'seasonal';

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

    public function tasks(): HasMany
    {
        return $this->hasMany(\App\Models\CommitteeTask::class, 'committee_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(\App\Models\FacultyLoading\SchoolYear::class);
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Issuance::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function amendedFrom(): BelongsTo
    {
        return $this->belongsTo(Committee::class, 'amended_from_committee_id');
    }

    public function amendedInto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Committee::class, 'amended_from_committee_id');
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

    /** Not-yet-revoked committees (default listing scope). */
    public function scopeNotRevoked($query)
    {
        return $query->whereNull('revoked_at');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isSubCommittee(): bool
    {
        return $this->parent_committee_id !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** True when a seasonal committee's window has fully elapsed. */
    public function isSeasonEnded(): bool
    {
        return $this->scope_type === self::SCOPE_SEASONAL
            && $this->season_ends_at !== null
            && $this->season_ends_at->isPast();
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
