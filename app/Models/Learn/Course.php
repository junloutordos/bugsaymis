<?php

namespace App\Models\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'learn_courses';

    protected $fillable = [
        'subject_id', 'section_id', 'school_year_id', 'academic_term_id',
        'status', 'syllabus_body', 'cover_photo_s3_key', 'cover_preset',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'section_id' => 'integer',
        'school_year_id' => 'integer',
        'academic_term_id' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'learn_course_id')->orderBy('position');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(CourseAnnouncement::class, 'learn_course_id')->orderByDesc('posted_at');
    }

    public function isCurrentSchoolYear(): bool
    {
        return $this->schoolYear?->is_current === true;
    }

    public function isReadOnly(): bool
    {
        return ! $this->isCurrentSchoolYear();
    }

    /** @return array{steps: array<int, array{key: string, label: string, complete: bool}>, percent: int} */
    public function setupProgress(): array
    {
        $steps = [
            ['key' => 'syllabus', 'label' => 'Write a syllabus', 'complete' => filled($this->syllabus_body)],
            ['key' => 'modules', 'label' => 'Add a module', 'complete' => $this->modules->isNotEmpty()],
            ['key' => 'content', 'label' => 'Add content to a module', 'complete' => $this->modules->contains(fn ($m) => $m->items->isNotEmpty())],
            ['key' => 'published', 'label' => 'Publish the course', 'complete' => $this->status === 'published'],
        ];

        return [
            'steps' => $steps,
            'percent' => (int) round(collect($steps)->where('complete', true)->count() / count($steps) * 100),
        ];
    }

    /** @return array<int> */
    public function instructorIds(): array
    {
        return LoadAssignment::teaching()
            ->where('subject_id', $this->subject_id)
            ->where('section_id', $this->section_id)
            ->where('school_year_id', $this->school_year_id)
            ->where('academic_term_id', $this->academic_term_id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function canEdit(User $user): bool
    {
        if ($this->isReadOnly()) {
            return false;
        }
        if ($user->hasPermission('learn.course.view.all')) {
            return true;
        }

        return in_array((int) $user->id, $this->instructorIds(), true);
    }

    public function canView(User $user): bool
    {
        if ($user->hasPermission('learn.course.view.all')) {
            return true;
        }

        return in_array((int) $user->id, $this->instructorIds(), true);
    }

    public function isVisibleToStudent(int $studentId): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        return StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $this->school_year_id)
            ->where('section_id', $this->section_id)
            ->where('status', 'enrolled')
            ->exists();
    }
}
