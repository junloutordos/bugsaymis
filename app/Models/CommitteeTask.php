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

    /**
     * How often the assignee(s) are expected to submit an accomplishment
     * for this task. `varies`/`one_time` need no "next due" computation;
     * the others are computed from the latest accomplishment update.
     */
    public const FREQUENCIES = ['monthly', 'quarterly', 'end_of_rating_period', 'annually', 'varies', 'one_time'];

    protected $fillable = [
        'committee_id',
        'rating_period_id',
        'work_distribution_plan_id',
        'title',
        'description',
        'status',
        'priority',
        'submission_frequency',
        'due_date',
        'sort_order',
        'created_by',
        'auto_synced_from_roster',
        'completed_at',
    ];

    protected $casts = [
        'due_date'                => 'date:Y-m-d',
        'completed_at'            => 'datetime',
        'sort_order'               => 'integer',
        'auto_synced_from_roster' => 'boolean',
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

    /** Updates flagged as a formal accomplishment submission (not a plain progress note). */
    public function accomplishmentUpdates(): HasMany
    {
        return $this->hasMany(CommitteeTaskUpdate::class)->where('is_accomplishment', true)->latest();
    }

    public function latestAccomplishment(): ?CommitteeTaskUpdate
    {
        return $this->accomplishmentUpdates()->first();
    }

    /**
     * Next expected submission date for recurring cadences, computed from
     * the latest accomplishment update (falling back to the task's
     * creation date when none has been submitted yet). Returns null for
     * frequencies that don't recur (`varies`, `one_time`) or when no
     * cadence is set — the board shows no "Next due" badge in that case.
     */
    public function nextDueDate(): ?\Illuminate\Support\Carbon
    {
        if (! in_array($this->submission_frequency, ['monthly', 'quarterly', 'annually'], true)) {
            return null;
        }

        $last = $this->relationLoaded('accomplishmentUpdates')
            ? $this->accomplishmentUpdates->first()
            : $this->latestAccomplishment();

        $anchor = $last?->created_at ?? $this->created_at;
        if (! $anchor) {
            return null;
        }

        return match ($this->submission_frequency) {
            'monthly'   => $anchor->copy()->addMonth(),
            'quarterly' => $anchor->copy()->addMonths(3),
            'annually'  => $anchor->copy()->addYear(),
        };
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
