<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementVoucher extends Model
{
    protected $table = 'disbursement_vouchers';

    protected $fillable = [
        'dv_number', 'ors_id', 'po_number', 'activity_title', 'activity_date',
        'gross_amount', 'tax_amount', 'net_amount', 'payment_method',
        'created_by', 'status',
        'iar_number', 'ris_number', 'dr_number', 'delivery_accepted_at', 'ris_complete', 'supply_officer_id',
        'dv_prepared_by', 'dv_prepared_at',
        'division_chief_id', 'division_chief_elog_at',
        'division_clerk_id', 'division_clerk_at',
        'bookkeeper_id', 'bookkeeper_at', 'bookkeeper_remarks',
        'bookkeeper_returned_at', 'bookkeeper_return_reason',
        'accountant_id', 'accountant_at', 'accountant_remarks',
        'accountant_returned_at', 'accountant_return_reason',
        'ocd_sign_id', 'ocd_sign_at',
        'cashier_id', 'cashier_processed_at', 'cashier_amount',
        'ocd_payment_sign_at',
        'payment_released_at', 'payment_reference',
    ];

    protected $casts = [
        'activity_date'           => 'date:Y-m-d',
        'gross_amount'            => 'decimal:2',
        'tax_amount'              => 'decimal:2',
        'net_amount'              => 'decimal:2',
        'cashier_amount'          => 'decimal:2',
        'ris_complete'            => 'boolean',
        'delivery_accepted_at'    => 'datetime',
        'dv_prepared_at'          => 'datetime',
        'division_chief_elog_at'  => 'datetime',
        'division_clerk_at'       => 'datetime',
        'bookkeeper_at'           => 'datetime',
        'bookkeeper_returned_at'  => 'datetime',
        'accountant_at'           => 'datetime',
        'accountant_returned_at'  => 'datetime',
        'ocd_sign_at'             => 'datetime',
        'cashier_processed_at'    => 'datetime',
        'ocd_payment_sign_at'     => 'datetime',
        'payment_released_at'     => 'datetime',
    ];

    public function ors(): BelongsTo
    {
        return $this->belongsTo(OblRequest::class, 'ors_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplyOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supply_officer_id');
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dv_prepared_by');
    }

    public function divisionChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }

    public function divisionClerk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_clerk_id');
    }

    public function bookkeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bookkeeper_id');
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_id');
    }

    public function ocdSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ocd_sign_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'                 => 'Draft',
            'pending_delivery'      => 'Pending Delivery',
            'delivery_accepted'     => 'Delivery Accepted',
            'preparing'             => 'Preparing DV',
            'pending_bookkeeper'    => 'Pending Bookkeeper',
            'returned_bookkeeper'   => 'Returned by Bookkeeper',
            'pending_accountant'    => 'Pending Accountant',
            'returned_accountant'   => 'Returned by Accountant',
            'pending_ocd_sign'      => 'Pending Campus Director Signature',
            'pending_cashier'       => 'Pending Cashier',
            'pending_ocd_payment'   => 'Pending CD Payment Signature',
            'released'              => 'Payment Released',
            default                 => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
