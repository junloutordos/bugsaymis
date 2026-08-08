<?php

namespace App\Models\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'learn_courses';

    protected $fillable = [
        'subject_id', 'section_id', 'school_year_id', 'academic_term_id',
        'status', 'syllabus_body',
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
}
