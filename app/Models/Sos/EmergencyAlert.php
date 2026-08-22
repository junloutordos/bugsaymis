<?php

namespace App\Models\Sos;

use App\Models\User;
use App\Traits\HasNoticeAcknowledgments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyAlert extends Model
{
    use HasNoticeAcknowledgments;

    protected $fillable = [
        'title', 'message', 'severity', 'audience', 'status', 'source',
        'sos_alert_id', 'created_by', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function sosAlert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Active alerts visible to an employee — matches audience 'all' or 'employees'. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->active()->whereIn('audience', ['all', 'employees']);
    }

    /** Active alerts visible to the given non-employee group (students|parents). */
    public function scopeVisibleToAudienceGroup(Builder $query, string $group): Builder
    {
        return $query->active()->whereIn('audience', ['all', $group]);
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
