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

class CourseAnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;

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
        $this->stranger = User::factory()->create();

        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
    }

    public function test_instructor_can_post_announcement_stranger_cannot(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('learn.announcements.store', $this->course), ['title' => 'Welcome', 'body' => 'Hi class'])
            ->assertRedirect();
        $this->assertDatabaseHas('learn_course_announcements', ['title' => 'Welcome', 'posted_by' => $this->teacher->id]);

        $this->actingAs($this->stranger)
            ->post(route('learn.announcements.store', $this->course), ['title' => 'Hack', 'body' => 'x'])
            ->assertForbidden();
    }

    public function test_instructor_can_update_and_delete_announcement(): void
    {
        $announcement = $this->course->announcements()->create([
            'title' => 'Welcome', 'body' => 'Hi', 'posted_by' => $this->teacher->id, 'posted_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.announcements.update', $announcement), ['title' => 'Welcome!', 'body' => 'Hi again'])
            ->assertRedirect();
        $this->assertSame('Welcome!', $announcement->fresh()->title);

        $this->actingAs($this->teacher)
            ->delete(route('learn.announcements.destroy', $announcement))
            ->assertRedirect();
        $this->assertDatabaseMissing('learn_course_announcements', ['id' => $announcement->id]);
    }
}
