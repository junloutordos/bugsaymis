<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearnControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private Section $section;
    private Course $course;
    private int $studentId;
    private string $studentPisaysystemID;

    protected function setUp(): void
    {
        parent::setUp();

        // students is ENGINE=MyISAM (see 2026_06_03_000000_create_students_table.php)
        // and MyISAM ignores transactions, so RefreshDatabase's per-test rollback
        // never applies to it — rows inserted here persist for the rest of this
        // process's test run. A small per-class counter is NOT safe here: PHP
        // static properties are independent per class, so another test class
        // starting its own counter at 1 generates the *same* pisaysystemID
        // values, and firstOrFail() then silently picks up that other class's
        // stale row instead of this test's own. Use a wide random range instead
        // so collisions across the whole suite are negligible.
        $this->studentPisaysystemID = 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $this->section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
            'status' => 'published',
        ]);

        // Student model has `guarded = ['*']` (read-only elsewhere in the app) —
        // every existing test seeds the `students` table directly via DB::table(),
        // never `Student::create()`.
        $this->studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => $this->studentPisaysystemID, 'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $this->sy->id,
            'section_id' => $this->section->id, 'grade_level' => 8, 'status' => 'enrolled',
            'enrollment_date' => now()->toDateString(),
        ]);
    }

    private function loginAsStudent(): void
    {
        session(['student_pisaysystemID' => $this->studentPisaysystemID]);
    }

    public function test_enrolled_student_sees_the_published_course_in_the_index(): void
    {
        $this->loginAsStudent();

        $response = $this->get(route('student-portal.learn.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('StudentPortal/Learn/Index')
            ->has('courses', 1)
        );
    }

    public function test_draft_course_is_hidden_from_the_index(): void
    {
        $this->course->update(['status' => 'draft']);
        $this->loginAsStudent();

        $response = $this->get(route('student-portal.learn.index'));

        $response->assertInertia(fn ($page) => $page->has('courses', 0));
    }

    public function test_show_403s_when_student_is_not_enrolled_in_the_section(): void
    {
        $otherPisaysystemID = 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        DB::table('students')->insert([
            'pisaysystemID' => $otherPisaysystemID, 'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        session(['student_pisaysystemID' => $otherPisaysystemID]);

        $this->get(route('student-portal.learn.show', $this->course))->assertForbidden();
    }

    public function test_show_hides_unpublished_modules_and_items_even_in_a_published_course(): void
    {
        $publishedModule = $this->course->modules()->create(['title' => 'Visible', 'position' => 0, 'published_at' => now()]);
        $this->course->modules()->create(['title' => 'Hidden', 'position' => 1]);
        $page = \App\Models\Learn\Page::create(['title' => 'Intro', 'body' => '<p>Hi</p>']);
        $page->moduleItem()->create(['learn_module_id' => $publishedModule->id, 'position' => 0, 'published_at' => now()]);
        $page2 = \App\Models\Learn\Page::create(['title' => 'Hidden item']);
        $page2->moduleItem()->create(['learn_module_id' => $publishedModule->id, 'position' => 1]);

        $this->loginAsStudent();

        $response = $this->get(route('student-portal.learn.show', $this->course));

        $response->assertInertia(fn ($page) => $page
            ->has('course.modules', 1)
            ->has('course.modules.0.items', 1)
            ->where('course.modules.0.items.0.title', 'Intro')
        );
    }

    public function test_cover_proxy_streams_for_an_enrolled_student_but_403s_an_unenrolled_one(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        app(\App\Services\Learn\CourseCoverService::class)->upload($this->course, 'data:image/png;base64,' . base64_encode('bytes'));

        $this->loginAsStudent();
        $this->get(route('student-portal.learn.cover', $this->course))->assertOk();

        $otherPisaysystemID = 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        DB::table('students')->insert([
            'pisaysystemID' => $otherPisaysystemID, 'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        session(['student_pisaysystemID' => $otherPisaysystemID]);
        $this->get(route('student-portal.learn.cover', $this->course))->assertForbidden();
    }

    public function test_index_and_show_payloads_include_cover_fields(): void
    {
        $this->course->update(['cover_preset' => 'navy-radial']);
        $this->loginAsStudent();

        $this->get(route('student-portal.learn.index'))->assertInertia(fn ($page) => $page
            ->where('courses.0.cover_preset', 'navy-radial')
            ->where('courses.0.cover_photo_url', null)
        );

        $this->get(route('student-portal.learn.show', $this->course))->assertInertia(fn ($page) => $page
            ->where('course.cover_preset', 'navy-radial')
            ->where('course.cover_photo_url', null)
        );
    }
}
