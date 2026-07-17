<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClassScheduleSwapRequest extends Model
{
    protected $fillable = [
        'academic_term_id', 'source_schedule_id', 'requested_by', 'reason', 'preferences',
        'status', 'target_schedule_id', 'selected_proposal', 'reviewed_by', 'reviewed_at',
        'approval_batch_id', 'applied_at',
    ];

    protected $casts = [
        'preferences' => 'array',
        'selected_proposal' => 'array',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function sourceSchedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'source_schedule_id');
    }

    public function targetSchedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'target_schedule_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvalBatch()
    {
        return $this->belongsTo(ClassScheduleApprovalBatch::class, 'approval_batch_id');
    }
}
