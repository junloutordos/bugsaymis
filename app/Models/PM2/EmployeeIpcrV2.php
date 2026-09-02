<?php

namespace App\Models\PM2;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EmployeeIpcrV2 extends Model
{
    protected $table = 'employee_ipcrs_v2';

    public const STATUS_RATED = 'Rated';

    protected $fillable = [
        'user_id', 'rating_period_id', 'title', 'status', 'remarks',
        'target_approved_at', 'submitted_for_rating_at', 'submitted_rating_at',
        'final_numeric_rating', 'final_adjectival_rating',
    ];

    protected $casts = [
        'target_approved_at'      => 'datetime',
        'submitted_for_rating_at' => 'datetime',
        'submitted_rating_at'     => 'datetime',
        'final_numeric_rating'    => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ratingPeriod()
    {
        return $this->belongsTo(IpcrRatingPeriodV2::class, 'rating_period_id');
    }

    public function rows()
    {
        return $this->hasMany(EmployeeIpcrPlanV2::class, 'ipcr_id')->orderBy('function_type')->orderBy('id');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_RATED;
    }

    public function isPeriodClosed(): bool
    {
        return $this->ratingPeriod?->isClosed() === true;
    }

    public function isMutable(): bool
    {
        return ! $this->isFinalized() && ! $this->isPeriodClosed();
    }
}
