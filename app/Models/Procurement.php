<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Procurement extends Model
{
    protected $table = 'procurements';

    protected $fillable = [
        'pr_no', 'pr_date', 'purpose', 'fund_source', 'priority', 'remarks',
        'requested_by', 'status',
        'approved_by_budget_officer', 'date_approved_budget_officer', 'date_decline_budget_officer',
        'approved_by_procurement',    'date_approved_procurement',    'date_decline_procurement',
        'approved_by_division_chief', 'date_approved_division_chief', 'date_decline_division_chief',
        'ocd_approved_by', 'ocd_approve_date', 'ocd_decline_date',
        'budget_officer_remarks', 'procurement_remarks', 'division_chief_remarks', 'ocd_remarks',
    ];

    protected $casts = [
        'pr_date' => 'date',
        'date_approved_budget_officer' => 'date',
        'date_decline_budget_officer' => 'date',
        'date_approved_procurement' => 'date',
        'date_decline_procurement' => 'date',
        'date_approved_division_chief' => 'date',
        'date_decline_division_chief' => 'date',
        'ocd_approve_date' => 'date',
        'ocd_decline_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class, 'procurement_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
