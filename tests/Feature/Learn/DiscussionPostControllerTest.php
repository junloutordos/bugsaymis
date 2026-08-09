<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private Discussion $discussion;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
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
        $this->teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $this->discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
    }

    public function test_instructor_can_post_a_top_level_reply_and_a_nested_reply(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.discussion-posts.store', $this->discussion), [
            'body' => 'Top level reply',
        ])->assertRedirect();

        $top = $this->discussion->fresh()->posts()->where('body', 'Top level reply')->firstOrFail();
        $this->assertSame('faculty', $top->author_type);
        $this->assertSame($this->teacher->id, $top->author_id);

        $this->actingAs($this->teacher)->post(route('learn.discussion-posts.store', $this->discussion), [
            'parent_post_id' => $top->id, 'body' => 'Nested reply',
        ])->assertRedirect();

        $nested = $this->discussion->fresh()->posts()->where('body', 'Nested reply')->firstOrFail();
        $this->assertSame($top->id, $nested->parent_post_id);
    }

    public function test_parent_post_id_from_a_different_discussion_is_rejected(): void
    {
        $otherDiscussion = Discussion::create(['title' => 'Other', 'prompt' => 'P']);
        $otherPost = $otherDiscussion->posts()->create(['author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'x']);

        $this->actingAs($this->teacher)->post(route('learn.discussion-posts.store', $this->discussion), [
            'parent_post_id' => $otherPost->id, 'body' => 'Should fail',
        ])->assertStatus(422);
    }

    public function test_author_can_edit_their_own_post_but_not_someone_elses(): void
    {
        $post = $this->discussion->posts()->create(['author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'Original']);

        $this->actingAs($this->teacher)->put(route('learn.discussion-posts.update', $post), ['body' => 'Edited'])->assertRedirect();
        $this->assertSame('Edited', $post->fresh()->body);

        $otherTeacher = User::factory()->create();
        $this->actingAs($otherTeacher)->put(route('learn.discussion-posts.update', $post), ['body' => 'Hijacked'])->assertForbidden();
        $this->assertSame('Edited', $post->fresh()->body);
    }

    public function test_editing_a_deleted_post_is_rejected(): void
    {
        $post = $this->discussion->posts()->create([
            'author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'x',
            'is_deleted' => true, 'deleted_by_type' => 'faculty', 'deleted_by_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher)->put(route('learn.discussion-posts.update', $post), ['body' => 'Resurrected'])->assertForbidden();
    }

    public function test_author_can_delete_their_own_post_and_instructor_can_delete_anyones(): void
    {
        $studentId = mt_rand(1, 999999999);
        $studentPost = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => $studentId, 'body' => 'Student post']);

        $this->actingAs($this->teacher)->delete(route('learn.discussion-posts.destroy', $studentPost))->assertRedirect();
        $this->assertTrue($studentPost->fresh()->is_deleted);
        $this->assertSame('faculty', $studentPost->fresh()->deleted_by_type);
        $this->assertSame($this->teacher->id, $studentPost->fresh()->deleted_by_id);
    }

    public function test_stranger_cannot_post_edit_or_delete(): void
    {
        $stranger = User::factory()->create();
        $post = $this->discussion->posts()->create(['author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'x']);

        $this->actingAs($stranger)->post(route('learn.discussion-posts.store', $this->discussion), ['body' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.discussion-posts.update', $post), ['body' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('learn.discussion-posts.destroy', $post))->assertForbidden();
    }
}
