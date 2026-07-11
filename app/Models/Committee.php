<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'head_id', 'description', 'parent_committee_id', 'fiscal_year'];

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

    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'committee_user')->withPivot('task');
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

    public function isSubCommittee(): bool
    {
        return $this->parent_committee_id !== null;
    }

    /** Load units for a given role — uses FL columns if set, falls back to 0. */
    public function loadUnitsFor(string $role): float
    {
        return in_array($role, ['chairperson', 'co_chair'])
            ? (float) ($this->chairperson_load_units ?? 0)
            : (float) ($this->member_load_units ?? 0);
    }
}
