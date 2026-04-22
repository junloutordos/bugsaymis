<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCreditRecord extends Model
{
    protected $table = 'service_credit_records';

    protected $fillable = [
        'user_id',
        'service_date',
        'service_type',
        'hours_rendered',
        'days_equivalent',
        'status',
        'leave_application_id',
        'approved_by',
        'approved_at',
        'expires_at',
        'remarks',
    ];

    protected $casts = [
        'service_date'    => 'date',
        'approved_at'     => 'date',
        'expires_at'      => 'date',
        'hours_rendered'  => 'decimal:2',
        'days_equivalent' => 'decimal:4',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert raw hours to days equivalent per CSC rules:
     * round down to nearest 0.5 day.
     */
    public static function hoursToDays(float $hours): float
    {
        $raw = $hours / 8;
        return floor($raw * 2) / 2;   // round down to nearest 0.5
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeActive($query)
    {
        // Approved, not consumed, not expired
        return $query->where('status', 'approved')
                     ->where(fn ($q) => $q->whereNull('expires_at')
                                          ->orWhere('expires_at', '>=', now()->toDateString()));
    }
}
