<?php

namespace App\Models\FacultyLoading;

use App\Models\DigitalSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClassScheduleApprovalBatch extends Model
{
    protected $fillable = [
        'academic_term_id', 'status', 'approval_type', 'schedule_snapshot', 'schedule_hash',
        'baseline_hash', 'change_set', 'supersedes_batch_id',
        'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'returned_by', 'returned_at', 'return_remarks',
    ];

    protected $casts = [
        'schedule_snapshot' => 'array',
        'change_set' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function signatures()
    {
        return $this->morphMany(DigitalSignature::class, 'signable');
    }

    public function supersedes()
    {
        return $this->belongsTo(self::class, 'supersedes_batch_id');
    }
}
