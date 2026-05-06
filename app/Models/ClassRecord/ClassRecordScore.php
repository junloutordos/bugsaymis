<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecordScore extends Model
{
    protected $table = 'class_record_scores';

    protected $fillable = [
        'class_record_student_id',
        'class_record_assessment_id',
        'score',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(ClassRecordStudent::class, 'class_record_student_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }
}
