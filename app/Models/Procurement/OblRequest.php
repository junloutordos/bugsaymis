<?php

namespace App\Models\Procurement;

use App\Models\Division;
use App\Models\Procurement;
use App\Models\User;
use App\Models\Procurement\DisbursementVoucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OblRequest extends Model
{
    protected $table = 'obligation_requests';

    protected $fillable = [
        'ors_number', 'procurement_id', 'purchase_order_id', 'po_number', 'supplier_name',
        'account_title', 'object_code', 'activity_title', 'activity_date', 'amount',
        'division_id', 'created_by', 'status',
        'logged_at', 'log_po_number', 'log_activity_title', 'log_activity_date', 'log_amount',
        'division_chief_id', 'division_chief_at',
        'budget_officer_id', 'budget_officer_at', 'budget_officer_remarks',
        'budget_officer_returned_at', 'budget_officer_return_reason',
        'bookkeeper_id', 'bookkeeper_at', 'bookkeeper_remarks',
        'bookkeeper_returned_at', 'bookkeeper_return_reason',
        'accountant_id', 'accountant_at', 'accountant_remarks',
        'ocd_id', 'ocd_at',
        'canvaser_id', 'canvaser_at',
    ];

    protected $casts = [
        'activity_date'               => 'date',
        'log_activity_date'           => 'date',
        'amount'                      => 'decimal:2',
        'log_amount'                  => 'decimal:2',
        'logged_at'                   => 'datetime',
        'division_chief_at'           => 'datetime',
        'budget_officer_at'           => 'datetime',
        'budget_officer_returned_at'  => 'datetime',
        'bookkeeper_at'               => 'datetime',
        'bookkeeper_returned_at'      => 'datetime',
        'accountant_at'               => 'datetime',
        'ocd_at'                      => 'datetime',
        'canvaser_at'                 => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function divisionChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }

    public function budgetOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_officer_id');
    }

    public function bookkeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bookkeeper_id');
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_id');
    }

    public function ocd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ocd_id');
    }

    public function canvaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canvaser_id');
    }

    public function disbursementVouchers(): HasMany
    {
        return $this->hasMany(DisbursementVoucher::class, 'ors_id');
    }

    public function isLogged(): bool
    {
        return $this->logged_at !== null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'                => 'Draft',
            'pending_dc'           => 'Pending Division Chief',
            'pending_bo'           => 'Pending Budget Officer',
            'returned_bo'          => 'Returned by Budget Officer',
            'pending_bookkeeper'   => 'Pending Bookkeeper',
            'returned_bookkeeper'  => 'Returned by Bookkeeper',
            'pending_accountant'   => 'Pending Accountant',
            'pending_ocd'          => 'Pending Campus Director',
            'at_canvaser'          => 'At Canvaser',
            'completed'            => 'Completed',
            default                => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
