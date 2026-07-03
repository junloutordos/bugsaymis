<?php

namespace App\Models\StudentClearance;

use App\Models\ClassRecord\ClassRecord;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentClearanceItem extends Model
{
    protected $fillable = [
        'student_clearance_id',
        'requirement_code',
        'requirement_label',
        'requirement_type',
        'requirement_group',
        'subject_id',
        'class_record_id',
        'load_assignment_id',
        'assigned_user_id',
        'assigned_permission',
        'status',
        'remarks',
        'accountability',
        'signed_by',
        'signed_at',
        'sort_order',
    ];

    protected $casts = [
        'student_clearance_id' => 'integer',
        'subject_id'           => 'integer',
        'class_record_id'      => 'integer',
        'load_assignment_id'   => 'integer',
        'assigned_user_id'     => 'integer',
        'signed_by'            => 'integer',
        'signed_at'            => 'datetime',
        'sort_order'           => 'integer',
    ];

    public function clearance(): BelongsTo
    {
        return $this->belongsTo(StudentClearance::class, 'student_clearance_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classRecord(): BelongsTo
    {
        return $this->belongsTo(ClassRecord::class);
    }

    public function loadAssignment(): BelongsTo
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
