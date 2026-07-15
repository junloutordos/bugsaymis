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

    /**
     * Base query joining assessments up to their class record, scoped to a
     * single school year. Single source of truth for the "max 3 assessments
     * per section per day" rule's underlying join.
     */
    public static function schoolYearScopeQuery(int $schoolYearId)
    {
        return static::query()
            ->join('class_record_quarters as crq', 'class_record_assessments.class_record_quarter_id', '=', 'crq.id')
            ->join('class_records as cr', 'crq.class_record_id', '=', 'cr.id')
            ->where('cr.school_year_id', $schoolYearId);
    }

    public static function sectionScopeQuery(int $sectionId, int $schoolYearId)
    {
        return static::schoolYearScopeQuery($schoolYearId)
            ->where('cr.section_id', $sectionId);
    }

    public static function countForSectionOnDate(int $sectionId, int $schoolYearId, string $date, array $excludeIds = []): int
    {
        return static::sectionScopeQuery($sectionId, $schoolYearId)
            ->where('class_record_assessments.activity_date', $date)
            ->whereNotIn('class_record_assessments.id', $excludeIds)
            ->count();
    }
}
