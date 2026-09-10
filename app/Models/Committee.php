<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'head_id', 'description', 'parent_committee_id', 'fiscal_year',
        'scope_type', 'school_year_id', 'season_starts_at', 'season_ends_at',
        'revoked_at', 'revoked_by', 'revocation_reason', 'amended_from_committee_id',
        'so_number', 'issuance_id', 'max_members',
        'chairperson_load_units', 'member_load_units',
    ];

    protected $casts = [
        'season_starts_at' => 'date',
        'season_ends_at'   => 'date',
        'revoked_at'       => 'datetime',
        'chairperson_load_units' => 'decimal:2',
        'member_load_units'      => 'decimal:2',
    ];

    public const SCOPE_PERPETUAL   = 'perpetual';
    public const SCOPE_SCHOOL_YEAR = 'school_year';
    public const SCOPE_SEASONAL    = 'seasonal';

    // NULL fiscal_year = applies to all years (legacy rows; committees are shared with Faculty Loading)
    public function scopeForFiscalYear($query, ?int $year)
    {
        if (! $year) {
            return $query;
        }

        return $query->where(function ($q) use ($year) {
            $q->whereNull('fiscal_year')->orWhere('fiscal_year', $year);
        });
    }

    /** Not-yet-revoked committees (default listing scope). */
    public function scopeNotRevoked($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'committee_user')->withPivot(['task', 'role', 'load_units_override']);
    }

    public function workDistributionPlans()
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'committee_work_distribution_plan');
    }

    public function parentCommittee()
    {
        return $this->belongsTo(Committee::class, 'parent_committee_id');
    }

    public function subCommittees()
    {
        return $this->hasMany(Committee::class, 'parent_committee_id');
    }

    public function tasks()
    {
        return $this->hasMany(CommitteeTask::class);
    }

    public function schoolYear()
    {
        return $this->belongsTo(\App\Models\FacultyLoading\SchoolYear::class);
    }

    public function issuance()
    {
        return $this->belongsTo(Issuance::class);
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function amendedFrom()
    {
        return $this->belongsTo(Committee::class, 'amended_from_committee_id');
    }

    /** The new committee that amended/replaced this one, if any. */
    public function amendedInto()
    {
        return $this->hasOne(Committee::class, 'amended_from_committee_id');
    }

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

    /** Load units for a given role — uses FL columns if set, falls back to 0. */
    public function loadUnitsFor(string $role): float
    {
        return in_array($role, ['chairperson', 'co_chair'])
            ? (float) ($this->chairperson_load_units ?? 0)
            : (float) ($this->member_load_units ?? 0);
    }
}
