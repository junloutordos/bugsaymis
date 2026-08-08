<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\Learn\CourseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseResolverTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private Section $section;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $this->section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
        $this->subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
    }

    private function assignTeaching(User $user, ?Subject $subject = null, ?Section $section = null): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching',
            'subject_id' => ($subject ?? $this->subject)->id,
            'section_id' => ($section ?? $this->section)->id,
            'load_units' => 3,
        ]);
    }

    public function test_resolver_creates_a_course_for_a_teaching_assignment(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        $courses = app(CourseResolver::class)->coursesForFaculty($teacher);

        $this->assertCount(1, $courses);
        $this->assertSame($this->subject->id, $courses->first()->subject_id);
        $this->assertDatabaseCount('learn_courses', 1);
    }

    public function test_resolver_is_idempotent_and_does_not_duplicate_the_course(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        app(CourseResolver::class)->coursesForFaculty($teacher);
        app(CourseResolver::class)->coursesForFaculty($teacher);

        $this->assertDatabaseCount('learn_courses', 1);
    }

    /**
     * load_assignments enforces UNIQUE(academic_term_id, subject_id, section_id)
     * for teaching slots — a Learn course (keyed by that same tuple) can only
     * ever have exactly one teaching LoadAssignment, so instructorIds() always
     * resolves to a single teacher. Real PEHM-style co-teaching happens across
     * three separate *subjects* sharing one Class Record, not multiple teachers
     * on the same subject/section/term — Learn treats those as three distinct
     * single-teacher courses, one per subject.
     */
    public function test_instructor_ids_resolves_the_teaching_assignments_user(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        $course = app(CourseResolver::class)->coursesForFaculty($teacher)->first();

        $this->assertSame([$teacher->id], $course->instructorIds());
    }

    public function test_can_edit_true_for_instructor_false_for_stranger(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = app(CourseResolver::class)->coursesForFaculty($teacher)->first();

        $this->assertTrue($course->canEdit($teacher));
        $this->assertFalse($course->canEdit($stranger));
    }

    public function test_past_school_year_course_is_read_only_even_for_its_instructor(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = app(CourseResolver::class)->coursesForFaculty($teacher)->first();

        // Flip to a past SY only after the course already exists — coursesForFaculty()
        // requires a current SY to resolve anything, so flipping first would leave
        // nothing to fetch.
        $this->sy->update(['is_current' => false]);

        $this->assertTrue($course->fresh()->isReadOnly());
        $this->assertFalse($course->fresh()->canEdit($teacher));
    }

    public function test_is_visible_to_student_requires_published_and_active_enrollment(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = app(CourseResolver::class)->coursesForFaculty($teacher)->first();

        $studentId = 555;
        $this->assertFalse($course->isVisibleToStudent($studentId), 'draft course must not be visible');

        $course->update(['status' => 'published']);
        $this->assertFalse($course->isVisibleToStudent($studentId), 'not enrolled yet');

        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $this->sy->id,
            'section_id' => $this->section->id, 'grade_level' => 8, 'status' => 'enrolled',
            'enrollment_date' => now()->toDateString(),
        ]);
        $this->assertTrue($course->fresh()->isVisibleToStudent($studentId));
    }

    public function test_all_courses_for_current_school_year_includes_every_teaching_tuple(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        $subjectB = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'MATH8', 'name' => 'Math 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->assignTeaching($teacherA);
        $this->assignTeaching($teacherB, $subjectB);

        $courses = app(CourseResolver::class)->allCoursesForCurrentSchoolYear();

        $this->assertCount(2, $courses);
    }
}
