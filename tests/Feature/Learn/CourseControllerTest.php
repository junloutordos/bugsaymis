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

    public function test_setup_progress_reports_each_step_and_overall_percent(): void
    {
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $progress = $course->setupProgress();
        $this->assertSame(0, $progress['percent']);
        $this->assertFalse(collect($progress['steps'])->firstWhere('key', 'syllabus')['complete']);

        $course->update(['syllabus_body' => '<p>Welcome</p>', 'status' => 'published']);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 1]);

        $progress = $course->fresh()->setupProgress();
        $this->assertSame(75, $progress['percent']); // syllabus + modules + published, no module content yet
        $this->assertTrue(collect($progress['steps'])->firstWhere('key', 'modules')['complete']);
        $this->assertFalse(collect($progress['steps'])->firstWhere('key', 'content')['complete']);

        $module->items()->create(['itemable_type' => \App\Models\Learn\Page::class, 'itemable_id' => 1, 'position' => 1]);

        $progress = $course->fresh()->load('modules.items')->setupProgress();
        $this->assertSame(100, $progress['percent']);
    }

    public function test_instructor_can_set_a_cover_preset_but_stranger_cannot(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($teacher)
            ->put(route('learn.cover.update', $course), ['preset' => 'sky-wave'])
            ->assertRedirect();
        $this->assertSame('sky-wave', $course->fresh()->cover_preset);

        $this->actingAs($stranger)
            ->put(route('learn.cover.update', $course), ['preset' => 'ocean-deep'])
            ->assertForbidden();
    }

    public function test_instructor_can_upload_a_cover_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        $dataUri = 'data:image/png;base64,' . base64_encode('fake png bytes');

        $this->actingAs($teacher)
            ->put(route('learn.cover.update', $course), ['photo_base64' => $dataUri])
            ->assertRedirect();

        $this->assertNotNull($course->fresh()->cover_photo_s3_key);
    }

    public function test_cover_proxy_streams_the_photo_for_a_viewer_but_403s_a_stranger(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        app(\App\Services\Learn\CourseCoverService::class)->upload($course, 'data:image/png;base64,' . base64_encode('bytes'));

        $this->actingAs($teacher)->get(route('learn.cover.show', $course))->assertOk();
        $this->actingAs($stranger)->get(route('learn.cover.show', $course))->assertForbidden();
    }

    public function test_index_payload_includes_cover_and_setup_percent(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        $response = $this->actingAs($teacher)->get(route('learn.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Index')
            ->where('courses.0.cover_preset', null)
            ->where('courses.0.cover_photo_url', null)
            ->where('courses.0.setup_percent', 0)
        );
    }

    public function test_show_payload_includes_cover_and_setup_progress(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'cover_preset' => 'sky-wave',
        ]);

        $response = $this->actingAs($teacher)->get(route('learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Show')
            ->where('course.cover_preset', 'sky-wave')
            ->where('course.cover_photo_url', null)
            ->has('course.setup_progress.steps', 4)
            ->where('course.setup_progress.percent', 0)
        );
    }
}
