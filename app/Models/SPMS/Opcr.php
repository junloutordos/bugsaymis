<?php

namespace App\Models\SPMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opcr extends Model
{
    use HasFactory;

    protected $table = 'spms_opcrs';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SUBMITTED_TO_ED = 'Submitted to Executive Director';
    public const STATUS_ED_APPROVED = 'ED Approved';
    public const STATUS_RETURNED = 'Returned';

    protected $fillable = [
        'fiscal_period_id', 'ratee_user_id', 'approver_user_id',
        'status', 'weight_profile_id',
        'rolled_up_rating', 'override_rating', 'override_reason',
        'final_rating', 'final_adjectival', 'return_reason', 'approved_by',
        'submitted_to_ed_at', 'approved_at',
    ];

    protected $casts = [
        'rolled_up_rating' => 'decimal:2',
        'override_rating' => 'decimal:2',
        'final_rating' => 'decimal:2',
        'submitted_to_ed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function weightProfile(): BelongsTo
    {
        return $this->belongsTo(WeightProfile::class, 'weight_profile_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(OpcrTarget::class, 'opcr_id');
    }
}
