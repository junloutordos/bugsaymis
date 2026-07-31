<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecordAssessmentDate extends Model
{
    protected $fillable = [
        'class_record_assessment_id',
        'activity_date',
        'plotted_at',
        'is_primary',
    ];

    protected $casts = [
        'activity_date' => 'date:Y-m-d',
        'plotted_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }
}
