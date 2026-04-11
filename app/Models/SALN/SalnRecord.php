<?php

namespace App\Models\SALN;

use App\Models\User;
use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalnRecord extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'saln_records';

    protected $fillable = [
        'user_id',
        'year',
        'as_of_date',
        'status',
        'total_real_properties',
        'total_personal_properties',
        'total_assets',
        'total_liabilities',
        'net_worth',
        'spouse_name',
        'spouse_position',
        'spouse_office',
        'spouse_government_id',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'filed_at',
        'reviewed_by',
        'approved_by',
        'filed_by',
    ];

    protected $casts = [
        'year'                      => 'integer',
        'as_of_date'                => 'date',
        'total_real_properties'     => 'decimal:2',
        'total_personal_properties' => 'decimal:2',
        'total_assets'              => 'decimal:2',
        'total_liabilities'         => 'decimal:2',
        'net_worth'                 => 'decimal:2',
        'submitted_at'              => 'datetime',
        'reviewed_at'               => 'datetime',
        'approved_at'               => 'datetime',
        'filed_at'                  => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function realProperties(): HasMany
    {
        return $this->hasMany(SalnRealProperty::class, 'saln_record_id');
    }

    public function personalProperties(): HasMany
    {
        return $this->hasMany(SalnPersonalProperty::class, 'saln_record_id');
    }

    public function liabilities(): HasMany
    {
        return $this->hasMany(SalnLiability::class, 'saln_record_id');
    }

    public function businessInterests(): HasMany
    {
        return $this->hasMany(SalnBusinessInterest::class, 'saln_record_id');
    }

    public function relatives(): HasMany
    {
        return $this->hasMany(SalnRelative::class, 'saln_record_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SalnReview::class, 'saln_record_id')->orderBy('created_at');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool       { return $this->status === 'draft'; }
    public function isSubmitted(): bool   { return $this->status === 'submitted'; }
    public function isUnderReview(): bool { return $this->status === 'under_review'; }
    public function isApproved(): bool    { return $this->status === 'approved'; }
    public function isReturned(): bool    { return $this->status === 'returned'; }
    public function isFiled(): bool       { return $this->status === 'filed'; }

    /** Employee can edit only when draft or returned */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'returned']);
    }

    /** Employee can submit only when draft or returned */
    public function isSubmittable(): bool
    {
        return $this->isEditable();
    }

    // ─── Net Worth Computation ────────────────────────────────────────────────

    /**
     * Recompute all financial totals from related rows and persist.
     * Call after any add/update/delete on related property or liability rows.
     */
    public function recomputeTotals(): void
    {
        $realTotal     = $this->realProperties()->sum('current_fair_market_value');
        $personalTotal = $this->personalProperties()->sum('acquisition_cost');
        $totalAssets   = $realTotal + $personalTotal;
        $totalLiab     = $this->liabilities()->sum('outstanding_balance');

        $this->update([
            'total_real_properties'     => $realTotal,
            'total_personal_properties' => $personalTotal,
            'total_assets'              => $totalAssets,
            'total_liabilities'         => $totalLiab,
            'net_worth'                 => $totalAssets - $totalLiab,
        ]);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /** Human-readable status label */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'        => 'Draft',
            'submitted'    => 'Submitted',
            'under_review' => 'Under Review',
            'approved'     => 'Approved',
            'returned'     => 'Returned for Revision',
            'filed'        => 'Filed',
            default        => ucfirst($this->status),
        };
    }

    /** Badge color class for frontend */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft'        => 'gray',
            'submitted'    => 'blue',
            'under_review' => 'amber',
            'approved'     => 'green',
            'returned'     => 'red',
            'filed'        => 'indigo',
            default        => 'gray',
        };
    }

    // ─── Audit Log Helper ─────────────────────────────────────────────────────

    /**
     * Append an entry to the immutable audit trail.
     */
    public function logAction(
        int    $actorId,
        string $action,
        string $statusFrom,
        string $statusTo,
        ?string $remarks = null
    ): SalnReview {
        return $this->reviews()->create([
            'actor_id'    => $actorId,
            'action'      => $action,
            'status_from' => $statusFrom,
            'status_to'   => $statusTo,
            'remarks'     => $remarks,
        ]);
    }
}
