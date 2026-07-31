<?php

namespace App\Models\ALP;

use App\Models\AMS\Activity as AmsActivity;
use App\Models\User;
use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlpActivity extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'alp_activities';

    protected $fillable = [
        'alp_program_cycle_id', 'ams_activity_id', 'title', 'activity_type', 'start_date', 'start_time',
        'end_date', 'end_time', 'learning_outcomes', 'target_participants', 'venue', 'is_off_campus',
        'budget_amount', 'resources_needed', 'status', 'risk_level', 'risk_data', 'consent_required',
        'completion_highlights', 'accomplishments', 'recommendations', 'prepared_by', 'approved_by',
        'approved_at', 'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'is_off_campus' => 'boolean',
        'budget_amount' => 'decimal:2', 'risk_data' => 'array', 'consent_required' => 'boolean',
        'approved_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function amsActivity(): BelongsTo
    {
        return $this->belongsTo(AmsActivity::class, 'ams_activity_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AlpSession::class);
    }
}
