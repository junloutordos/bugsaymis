<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeIPCR extends Model
{
    use HasFactory;

    protected $table = 'employee_ipcrs';

    public const STATUS_DIRECTOR_SIGNED = 'Director Signed';

    protected $fillable = [
        'user_id',
        'rating_period',
        'rating_period_id',
        'title',
        'status',
        'remarks',
        'submitted_for_review_at',
        'target_approved_at',
        'submitted_for_rating_at',
        'submitted_rating_at',
        'submitted_for_pmtreview_at',
        'submitted_to_hr_at',
        'director_signed_at',
        'director_signature',
        'final_numeric_rating',
        'final_adjectival_rating',
        'appealed_at',
        'appeal_remarks',
    ];

    protected $casts = [
        'submitted_for_review_at'    => 'datetime',
        'target_approved_at'         => 'datetime',
        'submitted_for_rating_at'    => 'datetime',
        'submitted_rating_at'        => 'datetime',
        'submitted_for_pmtreview_at' => 'datetime',
        'submitted_to_hr_at'         => 'datetime',
        'director_signed_at'         => 'datetime',
        'appealed_at'                => 'datetime',
        'final_numeric_rating'       => 'decimal:2',
    ];

    // Each IPCR belongs to a user (employee)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Named `period` (not `ratingPeriod`) on purpose: Laravel serializes a
     * `ratingPeriod` relation to the JSON key `rating_period`, which would
     * clobber the legacy `rating_period` string column the frontend reads.
     */
    public function period()
    {
        return $this->belongsTo(IPCRRatingPeriod::class, 'rating_period_id');
    }

    public function coachingSessions()
    {
        return $this->hasMany(IPCRCoachingSession::class, 'employee_ipcr_id')->orderBy('meeting_date', 'desc');
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

    // Pivot relationship
    public function plans()
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'employee_ipcrs_plan', 'ipcr_id', 'plan_id')
                    ->withPivot([
                        'accomplishment',
                        'mov_link',
                        'individual_target',

                        'self_quality',
                        'self_efficiency',
                        'self_timeliness',
                        'self_average',

                        'sup_quality',
                        'sup_efficiency',
                        'sup_timeliness',
                        'sup_average',

                        'remarks',
                    ])
                    ->withTimestamps();
    }
}
