<?php

namespace App\Models\Supply;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitionIssueSlip extends Model
{
    protected $table = 'requisition_issue_slips';

    protected $fillable = [
        'ris_number', 'division_id', 'purpose', 'date_requested', 'date_needed',
        'requested_by', 'approved_by', 'approved_at', 'approval_remarks',
        'issued_by', 'issued_at', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'date_requested' => 'date',
        'date_needed'    => 'date',
        'approved_at'    => 'datetime',
        'issued_at'      => 'datetime',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RisItem::class, 'ris_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'            => 'Draft',
            'pending'          => 'Pending Approval',
            'approved'         => 'Approved',
            'partially_issued' => 'Partially Issued',
            'fully_issued'     => 'Fully Issued',
            'cancelled'        => 'Cancelled',
            default            => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeIssued(): bool
    {
        return in_array($this->status, ['approved', 'partially_issued']);
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $seq   = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return sprintf('RIS-%s-%s-%04d', $year, $month, $seq);
    }
}
