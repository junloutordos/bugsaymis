<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private Discussion $discussion;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $this->discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $this->discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$this->studentId}"]);
    }

    public function test_student_can_post_a_top_level_reply_and_a_nested_reply(): void
    {
        $this->post(route('student-portal.learn.discussion-posts.store', $this->discussion), [
            'body' => 'Top level',
        ])->assertRedirect();

        $top = $this->discussion->fresh()->posts()->where('body', 'Top level')->firstOrFail();
        $this->assertSame('student', $top->author_type);
        $this->assertSame($this->studentId, $top->author_id);

        $this->post(route('student-portal.learn.discussion-posts.store', $this->discussion), [
            'parent_post_id' => $top->id, 'body' => 'Nested',
        ])->assertRedirect();

        $this->assertSame($top->id, $this->discussion->fresh()->posts()->where('body', 'Nested')->firstOrFail()->parent_post_id);
    }

    public function test_student_can_edit_their_own_post_but_not_someone_elses(): void
    {
        $post = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => $this->studentId, 'body' => 'Original']);

        $this->put(route('student-portal.learn.discussion-posts.update', $post), ['body' => 'Edited'])->assertRedirect();
        $this->assertSame('Edited', $post->fresh()->body);

        $facultyPost = $this->discussion->posts()->create(['author_type' => 'faculty', 'author_id' => 1, 'body' => 'Teacher post']);
        $this->put(route('student-portal.learn.discussion-posts.update', $facultyPost), ['body' => 'Hijacked'])->assertForbidden();
    }

    public function test_editing_a_deleted_post_is_rejected(): void
    {
        $post = $this->discussion->posts()->create([
            'author_type' => 'student', 'author_id' => $this->studentId, 'body' => 'x',
            'is_deleted' => true, 'deleted_by_type' => 'student', 'deleted_by_id' => $this->studentId,
        ]);

        $this->put(route('student-portal.learn.discussion-posts.update', $post), ['body' => 'Resurrected'])->assertForbidden();
    }

    public function test_student_can_delete_their_own_post_but_not_another_students(): void
    {
        $post = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => $this->studentId, 'body' => 'x']);
        $this->delete(route('student-portal.learn.discussion-posts.destroy', $post))->assertRedirect();
        $this->assertTrue($post->fresh()->is_deleted);
        $this->assertSame('student', $post->fresh()->deleted_by_type);
        $this->assertSame($this->studentId, $post->fresh()->deleted_by_id);

        $otherStudentPost = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => 999999, 'body' => 'y']);
        $this->delete(route('student-portal.learn.discussion-posts.destroy', $otherStudentPost))->assertForbidden();
    }
}
