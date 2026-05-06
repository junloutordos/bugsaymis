<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRecordAssessment extends Model
{
    protected $table = 'class_record_assessments';

    protected $fillable = [
        'class_record_quarter_id',
        'grading_category_id',
        'assessment_number',
        'title',
        'activity_date',
        'max_score',
        'sort_order',
    ];

    protected $casts = [
        'assessment_number' => 'integer',
        'activity_date'     => 'date:Y-m-d',
        'max_score'         => 'decimal:2',
        'sort_order'        => 'integer',
    ];

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(ClassRecordQuarter::class, 'class_record_quarter_id');
    }

    public function gradingCategory(): BelongsTo
    {
        return $this->belongsTo(GradingCategory::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ClassRecordScore::class);
    }
}
