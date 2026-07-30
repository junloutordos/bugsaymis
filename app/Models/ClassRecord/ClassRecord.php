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
        'category_label',
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

    protected $appends = ['display_name'];

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

    /**
     * Co-teacher pivot rows for this record — currently only populated for
     * shared PEHM (PE/Health/Music) records. Empty for every normal
     * single-teacher record.
     */
    public function coTeachers(): HasMany
    {
        return $this->hasMany(ClassRecordTeacher::class);
    }

    /**
     * User IDs allowed to write to the given subject's portion of this
     * record. Falls back to the single teacher_id when there are no
     * co-teacher pivot rows (every existing/non-PEHM record) — the $subjectId
     * argument is ignored in that case, since there's nothing to scope.
     *
     * @return array<int>
     */
    public function teacherIdsFor(?int $subjectId = null): array
    {
        if (! $this->relationLoaded('coTeachers')) {
            $this->load('coTeachers');
        }

        if ($this->coTeachers->isEmpty()) {
            return $this->teacher_id ? [(int) $this->teacher_id] : [];
        }

        $rows = $subjectId === null
            ? $this->coTeachers
            : $this->coTeachers->where('subject_id', $subjectId);

        return $rows->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Every teacher who has any edit access to this record, across all subjects. */
    public function allTeacherIds(): array
    {
        if (! $this->relationLoaded('coTeachers')) {
            $this->load('coTeachers');
        }

        if ($this->coTeachers->isEmpty()) {
            return $this->teacher_id ? [(int) $this->teacher_id] : [];
        }

        return $this->coTeachers->pluck('user_id')
            ->push($this->teacher_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Can $user edit this record? Pass $subjectId to scope the check to one
     * subject's leaves/attendance on a shared record (e.g. the PE teacher may
     * only edit PE); omit it for record-wide actions (roster, submit,
     * archive) that any of the record's teachers may perform.
     */
    public function canEdit(User $user, ?int $subjectId = null): bool
    {
        if ($user->hasPermission('class-records.admin')) {
            return true;
        }

        return in_array((int) $user->id, $this->teacherIdsFor($subjectId), true);
    }

    /** Read-only access follows the same teacher set as canEdit(), subject-agnostic. */
    public function canView(User $user): bool
    {
        return $this->canEdit($user);
    }

    /**
     * Other class records that split the same subject+section+teacher this SY
     * into separate categories (e.g. STEM Research "Ongoing" vs "Completed").
     * There is no explicit link column — sibling-ness is derived from sharing
     * that 4-tuple, same as myTeachingLoad()'s existing-pair check.
     */
    public function siblingsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->where('subject_id', $this->subject_id)
            ->where('teacher_id', $this->teacher_id)
            ->where('school_year_id', $this->school_year_id)
            ->where('id', '<>', $this->id)
            ->where('status', '<>', 'archived')
            ->when(
                $this->section_id === null,
                fn ($q) => $q->whereNull('section_id'),
                fn ($q) => $q->where('section_id', $this->section_id),
            );
    }

    /** This record's display name, with its category label suffixed if set. */
    public function getDisplayNameAttribute(): string
    {
        return $this->category_label
            ? "{$this->subject_name} — {$this->category_label}"
            : $this->subject_name;
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
