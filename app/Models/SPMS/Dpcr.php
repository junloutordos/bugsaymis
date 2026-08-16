<?php

namespace App\Models\SPMS;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dpcr extends Model
{
    use HasFactory;

    protected $table = 'spms_dpcrs';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SUBMITTED_TO_REVIEWER = 'Submitted to Reviewer';
    public const STATUS_REVIEWED = 'Reviewed';
    public const STATUS_SUBMITTED_TO_APPROVER = 'Submitted to Approver';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_RETURNED = 'Returned';

    protected $fillable = [
        'division_id', 'fiscal_period_id', 'ratee_user_id', 'reviewer_user_id', 'approver_user_id',
        'status', 'weight_profile_id', 'unit_count',
        'rolled_up_rating', 'override_rating', 'override_reason',
        'final_rating', 'final_adjectival', 'return_reason',
        'submitted_to_reviewer_at', 'reviewed_at', 'submitted_to_approver_at', 'approved_at',
    ];

    protected $casts = [
        'rolled_up_rating' => 'decimal:2',
        'override_rating' => 'decimal:2',
        'final_rating' => 'decimal:2',
        'unit_count' => 'integer',
        'submitted_to_reviewer_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'submitted_to_approver_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function weightProfile(): BelongsTo
    {
        return $this->belongsTo(WeightProfile::class, 'weight_profile_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(DpcrTarget::class, 'dpcr_id');
    }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class, 'dpcr_id');
    }
}
