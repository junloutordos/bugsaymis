<?php

namespace App\Models;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use Illuminate\Database\Eloquent\Model;

class EmployeeFunction extends Model
{
    protected $table = 'employee_functions';

    public const TYPE_CORE = 'core';
    public const TYPE_SUPPORT = 'support';

    public const SOURCE_LOAD_ASSIGNMENT = 'load_assignment';
    public const SOURCE_WDP = 'wdp';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'user_id', 'function_type', 'source_type',
        'load_assignment_id', 'sync_source_key',
        'label', 'output_outcome', 'weight_percent', 'academic_term_id', 'created_by',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
    ];

    protected $appends = ['source_label'];

    /**
     * ipcr_v2_core_items/support_items.employee_function_id is
     * nullOnDelete — deleting this row alone leaves its already-
     * materialized IPCR V2 item behind forever (employee_function_id
     * goes null, but the row itself, with its label/target, stays put),
     * since nothing else ever re-checks for that on its own. Centralized
     * here (rather than duplicated in every controller/service that
     * deletes an EmployeeFunction — manual destroy, committee-deletion
     * cascade, future callers) so it's impossible to add a new deletion
     * path that forgets this cleanup. Only prunes when the owning IPCR V2
     * record is still mutable AND the item has no real accomplishment
     * data logged yet — never silently drops rated/logged work, matching
     * the same rule EmployeeFunctionSyncService's own cleanup follows.
     */
    /** @var array<int, array{core: array<int, int>, support: array<int, int>}> */
    private static array $pendingPrunableItemIds = [];

    protected static function booted(): void
    {
        // The FK's nullOnDelete fires as part of the SAME delete
        // statement — by the time a `deleted` event handler runs, these
        // rows' employee_function_id is already null, so querying by
        // `employee_function_id = $function->id` there would find
        // nothing. Capture the candidate row ids in `deleting` (still
        // BEFORE the delete/cascade happens) into a static map keyed by
        // id (not a dynamic model property — Eloquent's magic setter
        // would route that into $attributes, which is not what this is),
        // then prune them in `deleted` once this row settles.
        static::deleting(function (EmployeeFunction $function) {
            static::$pendingPrunableItemIds[$function->id] = [
                'core' => \App\Models\IPCRV2\IpcrV2CoreItem::where('employee_function_id', $function->id)->pluck('id')->all(),
                'support' => \App\Models\IPCRV2\IpcrV2SupportItem::where('employee_function_id', $function->id)->pluck('id')->all(),
            ];
        });

        static::deleted(function (EmployeeFunction $function) {
            $ids = static::$pendingPrunableItemIds[$function->id] ?? ['core' => [], 'support' => []];
            unset(static::$pendingPrunableItemIds[$function->id]);

            \App\Models\IPCRV2\IpcrV2CoreItem::whereIn('id', $ids['core'])
                ->where(fn ($q) => $q->whereNull('actual_accomplishment')->orWhere('actual_accomplishment', ''))
                ->with('ipcr')
                ->get()
                ->each(fn ($item) => $item->ipcr?->isMutable() && $item->delete());

            \App\Models\IPCRV2\IpcrV2SupportItem::whereIn('id', $ids['support'])
                ->where(fn ($q) => $q->whereNull('actual_accomplishment')->orWhere('actual_accomplishment', ''))
                ->with('ipcr')
                ->get()
                ->each(fn ($item) => $item->ipcr?->isMutable() && $item->delete());
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loadAssignment()
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function workDistributionPlans()
    {
        return $this->belongsToMany(
            WorkDistributionPlan::class,
            'employee_function_work_distribution_plan'
        );
    }

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ipcrV2CoreItems()
    {
        return $this->hasMany(\App\Models\IPCRV2\IpcrV2CoreItem::class);
    }

    public function ipcrV2SupportItems()
    {
        return $this->hasMany(\App\Models\IPCRV2\IpcrV2SupportItem::class);
    }

    public function scopeCore($query)
    {
        return $query->where('function_type', self::TYPE_CORE);
    }

    public function scopeSupport($query)
    {
        return $query->where('function_type', self::TYPE_SUPPORT);
    }

    public function scopeAutoSynced($query)
    {
        return $query->where('source_type', self::SOURCE_LOAD_ASSIGNMENT);
    }

    /**
     * A friendlier origin label for the UI than the raw source_type enum
     * (every auto-synced row shares source_type=load_assignment
     * regardless of whether it actually came from a teaching/designation
     * load, a committee assignment, or a personnel-assigned plan —
     * derived from sync_source_key's prefix, the same marker
     * EmployeeFunctionSyncService::upsertRow() keys on).
     */
    public function getSourceLabelAttribute(): string
    {
        if ($this->source_type !== self::SOURCE_LOAD_ASSIGNMENT || ! $this->sync_source_key) {
            return match ($this->source_type) {
                self::SOURCE_WDP => 'Work Distribution Plan',
                self::SOURCE_MANUAL => 'Manual',
                default => (string) $this->source_type,
            };
        }

        return match (true) {
            str_starts_with($this->sync_source_key, 'committee_assignment:') => 'Committee',
            str_starts_with($this->sync_source_key, 'personnel_plan:') => 'Personnel Plan',
            default => 'Load Assignment',
        };
    }
}
