<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClassRecordAssessment extends Model
{
    protected $table = 'class_record_assessments';

    public const TYPES = [
        'formative'   => 'Formative Assessment',
        'alternative' => 'Alternative Assessment',
        'ila'         => 'Independent Learning Activity',
        'long_test_1' => 'Long Test 1',
        'long_test_2' => 'Long Test 2',
    ];

    protected $fillable = [
        'class_record_quarter_id',
        'grading_category_id',
        'assessment_type',
        'is_graded',
        'is_major',
        'assessment_number',
        'title',
        'activity_date',
        'plotted_at',
        'max_score',
        'sort_order',
    ];

    protected $casts = [
        'assessment_number' => 'integer',
        'is_graded'         => 'boolean',
        'is_major'          => 'boolean',
        'activity_date'     => 'date:Y-m-d',
        'plotted_at'        => 'datetime',
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

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(ClassRecordAssessmentDeletionRequest::class);
    }

    public function pendingDeletionRequest(): HasOne
    {
        return $this->hasOne(ClassRecordAssessmentDeletionRequest::class)->where('status', 'pending');
    }

    /**
     * Base query joining assessments up to their class record, scoped to a
     * single school year. Single source of truth for the "max 3 assessments
     * per section per day" rule's underlying join.
     *
     * Archived class records are excluded: once a teacher archives a record
     * (status = 'archived') and plots on a fresh one, the archived record's
     * assessments must stop surfacing in the Weekly Assessment Tracker and
     * stop consuming the section's daily/weekly plotting budget — otherwise
     * they double-count against the new record. Restoring the record
     * (restore() flips status back) automatically brings them back.
     */
    public static function schoolYearScopeQuery(int $schoolYearId)
    {
        return static::query()
            ->join('class_record_quarters as crq', 'class_record_assessments.class_record_quarter_id', '=', 'crq.id')
            ->join('class_records as cr', 'crq.class_record_id', '=', 'cr.id')
            ->where('cr.school_year_id', $schoolYearId)
            ->where('cr.status', '<>', 'archived');
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
