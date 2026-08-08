<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseControllerTest extends TestCase
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

    private function assignTeaching(User $user): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $this->subject->id,
            'section_id' => $this->section->id, 'load_units' => 3,
        ]);
    }

    public function test_index_lists_the_teachers_courses_and_creates_them_lazily(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        $response = $this->actingAs($teacher)->get(route('learn.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Index')
            ->has('courses', 1)
            ->where('courses.0.subject_name', 'Science 8')
        );
        $this->assertDatabaseCount('learn_courses', 1);
    }

    public function test_show_403s_for_a_non_instructor(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($stranger)->get(route('learn.show', $course))->assertForbidden();
    }

    public function test_instructor_can_update_syllabus_but_stranger_cannot(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($teacher)
            ->put(route('learn.syllabus.update', $course), ['syllabus_body' => '<p>Welcome</p>'])
            ->assertRedirect();
        $this->assertSame('<p>Welcome</p>', $course->fresh()->syllabus_body);

        $this->actingAs($stranger)
            ->put(route('learn.syllabus.update', $course), ['syllabus_body' => '<p>Hacked</p>'])
            ->assertForbidden();
    }

    public function test_instructor_can_publish_and_unpublish_the_course(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($teacher)
            ->patch(route('learn.status.update', $course), ['status' => 'published'])
            ->assertRedirect();
        $this->assertSame('published', $course->fresh()->status);
    }

    public function test_past_school_year_course_cannot_be_edited_even_by_its_instructor(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        $this->sy->update(['is_current' => false]);

        $this->actingAs($teacher)
            ->put(route('learn.syllabus.update', $course), ['syllabus_body' => '<p>Too late</p>'])
            ->assertForbidden();
    }
}
