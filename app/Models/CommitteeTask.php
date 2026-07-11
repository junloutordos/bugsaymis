<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommitteeTask extends Model
{
    public const STATUSES = ['not_started', 'working_on_it', 'stuck', 'done'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'committee_id',
        'rating_period_id',
        'work_distribution_plan_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'sort_order',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'sort_order'   => 'integer',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'committee_task_user');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(CommitteeTaskUpdate::class)->latest();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkDistributionPlan::class, 'work_distribution_plan_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IPCRRatingPeriod::class, 'rating_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // NULL period = task applies to all periods
    public function scopeForPeriod($query, ?int $periodId)
    {
        if (! $periodId) {
            return $query;
        }

        return $query->where(function ($q) use ($periodId) {
            $q->whereNull('rating_period_id')->orWhere('rating_period_id', $periodId);
        });
    }
}
