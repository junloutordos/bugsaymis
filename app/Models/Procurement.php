<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Procurement extends Model
{
    protected $table = 'procurements';

    protected $guarded = [];

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
