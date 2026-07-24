<?php

namespace App\Models\ClassRecord;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRecord extends Model
{
    protected $table = 'class_records';

    protected $fillable = [
        'subject_id',
        'section_id',
        'teacher_id',
        'grading_option_id',
        'school_year_id',
        'school_year',
        'subject_name',
        'year_level_section',
        'status',
        'submitted_at',
        'checked_at',
        'checked_by_id',
        'archived_at',
        'archived_by_id',
        'pre_archive_status',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'section_id' => 'integer',
        'submitted_at' => 'datetime',
        'checked_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function isCurrentSchoolYear(): bool
    {
        return $this->schoolYear?->is_current === true;
    }

    public function hasScores(): bool
    {
        return ClassRecordScore::whereHas(
            'student.quarter.classRecord', fn ($q) => $q->where('id', $this->id)
        )->exists();
    }

    public function hasAssessments(): bool
    {
        return ClassRecordAssessment::whereHas(
            'quarter', fn ($q) => $q->where('class_record_id', $this->id)
        )->exists();
    }

    /**
     * Grading option may only be swapped while the record is still empty —
     * once assessments or scores exist under the current option, its
     * categories are load-bearing for already-entered data.
     */
    public function canChangeGradingOption(bool $isAdmin): bool
    {
        if (! $this->isCurrentSchoolYear()) {
            return false;
        }
        if ($this->status === 'submitted' && ! $isAdmin) {
            return false;
        }

        return ! $this->hasScores() && ! $this->hasAssessments();
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function gradingOption(): BelongsTo
    {
        return $this->belongsTo(GradingOption::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_id');
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Exclude archived (soft-deleted) records from active listings/analytics.
     * Whitelist the known active statuses so any archived, blank, or otherwise
     * invalid status is also excluded (defensive against enum/data drift),
     * not just an exact 'archived' match.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'submitted', 'checked']);
    }

    public function quarters(): HasMany
    {
        return $this->hasMany(ClassRecordQuarter::class)->orderBy('quarter');
    }
}
