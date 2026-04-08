<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveApplication extends Model
{
    use SoftDeletes;

    protected $table = 'leave_applications';

    protected $fillable = [
        'control_no',
        'user_id',
        'leave_type_id',
        'date_from',
        'date_to',
        'dates',
        'days_applied',
        'leave_details',
        'leave_details_specify',
        'reason',
        'supporting_document',
        'status',
        'division_chief_id',
        'division_chief_action',
        'division_chief_at',
        'division_chief_remarks',
        'approved_by',
        'approval_action',
        'approved_at',
        'approval_remarks',
        'days_deducted',
        'is_without_pay',
    ];

    protected $casts = [
        'date_from'           => 'date',
        'date_to'             => 'date',
        'dates'               => 'array',
        'days_applied'        => 'decimal:2',
        'days_deducted'       => 'decimal:2',
        'is_without_pay'      => 'boolean',
        'filed_at'            => 'datetime',
        'division_chief_at'   => 'datetime',
        'approved_at'         => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function divisionChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->control_no)) {
                $year  = now()->year;
                $count = static::whereYear('filed_at', $year)->count() + 1;
                $model->control_no = sprintf('LVE-%d-%05d', $year, $count);
            }
        });
    }
}
