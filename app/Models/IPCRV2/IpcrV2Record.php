<?php

namespace App\Models\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IpcrV2Record extends Model
{
    protected $table = 'ipcr_v2_records';

    public const STATUS_DIRECTOR_SIGNED = 'Director Signed';

    protected $fillable = [
        'user_id', 'rating_period_id', 'status',
        'submitted_for_review_at', 'target_approved_at', 'submitted_for_rating_at',
        'submitted_rating_at', 'submitted_for_pmtreview_at', 'submitted_to_hr_at',
        'director_signed_at', 'director_signature',
        'final_numeric_rating', 'final_adjectival_rating',
    ];

    protected $casts = [
        'submitted_for_review_at' => 'datetime',
        'target_approved_at' => 'datetime',
        'submitted_for_rating_at' => 'datetime',
        'submitted_rating_at' => 'datetime',
        'submitted_for_pmtreview_at' => 'datetime',
        'submitted_to_hr_at' => 'datetime',
        'director_signed_at' => 'datetime',
        'final_numeric_rating' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function period()
    {
        return $this->belongsTo(IPCRRatingPeriod::class, 'rating_period_id');
    }

    public function coreItems()
    {
        return $this->hasMany(IpcrV2CoreItem::class, 'ipcr_v2_id');
    }

    public function supportItems()
    {
        return $this->hasMany(IpcrV2SupportItem::class, 'ipcr_v2_id');
    }

    public function coachingSessions()
    {
        return $this->hasMany(IpcrV2CoachingSession::class, 'ipcr_v2_id')->orderBy('meeting_date', 'desc');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_DIRECTOR_SIGNED;
    }

    public function isPeriodClosed(): bool
    {
        return $this->period?->isClosed() === true;
    }

    public function isMutable(): bool
    {
        return ! $this->isFinalized() && ! $this->isPeriodClosed();
    }
}
