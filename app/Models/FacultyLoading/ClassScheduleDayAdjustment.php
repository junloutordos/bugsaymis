<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassScheduleDayAdjustment extends Model
{
    protected $fillable = [
        'academic_term_id',
        'postponed_from_date',
        'effective_date',
        'adjustment_type',
        'grade_levels',
        'ceremony_start_time',
        'ceremony_end_time',
        'shift_minutes',
        'activity_title',
        'activity_start_time',
        'activity_end_time',
        'class_duration_minutes',
        'reason',
        'status',
        'schedule_snapshot',
        'created_by',
        'published_by',
        'published_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'postponed_from_date' => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'grade_levels' => 'array',
        'shift_minutes' => 'integer',
        'class_duration_minutes' => 'integer',
        'schedule_snapshot' => 'array',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** All campus grade levels this module manages, in order. */
    public const ALL_GRADES = [7, 8, 9, 10, 11, 12];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(ClassScheduleDayAdjustmentOverride::class, 'adjustment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Grades this adjustment actually applies to. A null/empty column means
     * "all grades" — the pre-existing behavior for every adjustment created
     * before grade scoping existed, and the default for new ones too.
     *
     * @return array<int,int>
     */
    public function gradeLevels(): array
    {
        return $this->grade_levels ? array_values(array_map('intval', $this->grade_levels)) : self::ALL_GRADES;
    }

    public function appliesToGrade(int $gradeLevel): bool
    {
        return in_array($gradeLevel, $this->gradeLevels(), true);
    }

    public function hasFlagCeremony(): bool
    {
        return in_array($this->adjustment_type, ['flag_ceremony', 'flag_ceremony_shortened_classes'], true);
    }

    public function hasShortenedClasses(): bool
    {
        return in_array($this->adjustment_type, [
            'shortened_classes',
            'flag_ceremony_shortened_classes',
            'shortened_classes_protect_assessments',
        ], true);
    }

    public function protectsAssessmentPeriods(): bool
    {
        return $this->adjustment_type === 'shortened_classes_protect_assessments';
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('effective_date', $date);
    }
}
