<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Procurement extends Model
{
    protected $table = 'procurements';

    protected $fillable = [
        'pr_no', 'pr_date', 'purpose', 'fund_source', 'priority', 'remarks',
        'requested_by', 'status', 'division_id',
        'is_supplemental', 'ppmp_checked',
        'supplemental_status', 'supplemental_budget_officer_id', 'supplemental_budget_officer_at',
        'division_chief_id', 'division_chief_remarks', 'division_chief_at',
        'procurement_officer_id', 'procurement_officer_at', 'assigned_pr_number',
        'ocd_id', 'ocd_at', 'ocd_remarks',
        'rejected_by', 'rejected_at', 'rejection_reason',
        // Legacy columns kept for backward compat
        'approved_by_budget_officer', 'date_approved_budget_officer', 'date_decline_budget_officer',
        'approved_by_procurement', 'date_approved_procurement', 'date_decline_procurement',
        'approved_by_division_chief', 'date_approved_division_chief', 'date_decline_division_chief',
        'ocd_approved_by', 'ocd_approve_date', 'ocd_decline_date',
        'budget_officer_remarks', 'procurement_remarks', 'decline_reason',
    ];

    protected $casts = [
        'pr_date'                      => 'date',
        'is_supplemental'              => 'boolean',
        'ppmp_checked'                 => 'boolean',
        'supplemental_budget_officer_at' => 'datetime',
        'division_chief_at'            => 'datetime',
        'procurement_officer_at'       => 'datetime',
        'ocd_at'                       => 'datetime',
        'rejected_at'                  => 'datetime',
        // Legacy
        'date_approved_budget_officer' => 'date',
        'date_decline_budget_officer'  => 'date',
        'date_approved_procurement'    => 'date',
        'date_decline_procurement'     => 'date',
        'date_approved_division_chief' => 'date',
        'date_decline_division_chief'  => 'date',
        'ocd_approve_date'             => 'date',
        'ocd_decline_date'             => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class, 'procurement_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function divisionChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }

    public function procurementOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'procurement_officer_id');
    }

    public function ocd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ocd_id');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function obligationRequests(): HasMany
    {
        return $this->hasMany(\App\Models\Procurement\OblRequest::class, 'procurement_id');
    }

    public function isLegacy(): bool
    {
        return $this->status === 'approved' && $this->division_chief_id === null && $this->ocd_id === null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'                => 'Draft',
            'pending_dc'           => 'Pending Division Chief',
            'pending_procurement'  => 'Pending Procurement Officer',
            'pending_ocd'          => 'Pending Campus Director',
            'approved'             => 'Approved',
            'rejected'             => 'Rejected',
            // Supplemental sub-statuses
            'supplemental_pending_dc'  => 'Supplemental – Pending Division Chief',
            'supplemental_pending_bo'  => 'Supplemental – Pending Budget Officer',
            'supplemental_pending_ocd' => 'Supplemental – Pending Campus Director',
            default                => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
