<?php

namespace App\Models\ClassRecord;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecordAssessmentDeletionRequest extends Model
{
    protected $table = 'class_record_assessment_deletion_requests';

    protected $fillable = [
        'class_record_assessment_id',
        'class_record_assessment_date_id',
        'class_record_quarter_id',
        'title',
        'assessment_number',
        'category_code',
        'category_name',
        'activity_date',
        'max_score',
        'requested_by_id',
        'reason',
        'status',
        'reviewed_by_id',
        'reviewed_at',
        'review_remarks',
    ];

    protected $casts = [
        'assessment_number' => 'integer',
        'activity_date' => 'date:Y-m-d',
        'max_score' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }

    public function assessmentDate(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessmentDate::class, 'class_record_assessment_date_id');
    }

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(ClassRecordQuarter::class, 'class_record_quarter_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
