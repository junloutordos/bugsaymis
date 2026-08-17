<?php

namespace App\Models\SPMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ipcr extends Model
{
    use HasFactory;

    protected $table = 'spms_ipcrs';

    public const STATUS_DRAFT_TARGET = 'Draft Target';
    public const STATUS_TARGET_SUBMITTED = 'Target Submitted';
    public const STATUS_TARGET_APPROVED = 'Target Approved';
    public const STATUS_SUBMITTED_FOR_RATING = 'Submitted for Rating';
    public const STATUS_RATED = 'Rated';
    public const STATUS_DC_REVIEWED = 'DC Reviewed';
    public const STATUS_PMT_HR_REVIEWED = 'PMT/HR Reviewed';
    public const STATUS_DIRECTOR_SIGNED = 'Director Signed';
    public const STATUS_RETURNED = 'Returned';

    protected $fillable = [
        'user_id', 'fiscal_period_id', 'dpcr_id', 'status', 'weight_profile_id',
        'final_rating', 'final_adjectival', 'return_reason',
        'target_submitted_at', 'target_approved_at', 'submitted_for_rating_at',
        'rated_at', 'director_signed_at',
    ];

    protected $casts = [
        'final_rating' => 'decimal:2',
        'target_submitted_at' => 'datetime',
        'target_approved_at' => 'datetime',
        'submitted_for_rating_at' => 'datetime',
        'rated_at' => 'datetime',
        'director_signed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function weightProfile(): BelongsTo
    {
        return $this->belongsTo(WeightProfile::class, 'weight_profile_id');
    }

    public function dpcr(): BelongsTo
    {
        return $this->belongsTo(Dpcr::class, 'dpcr_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(IpcrTarget::class, 'ipcr_id');
    }
}
