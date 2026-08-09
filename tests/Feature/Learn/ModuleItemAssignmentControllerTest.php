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

class ModuleItemAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;
    private $module;

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
        $this->module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_instructor_can_add_a_flat_points_assignment(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Essay 1', 'instructions' => '<p>Write 500 words.</p>',
            'submission_type' => 'text', 'points_possible' => 50, 'due_at' => '2026-02-01 23:59:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_assignments', ['title' => 'Essay 1', 'points_possible' => 50]);
        $this->assertDatabaseHas('learn_module_items', [
            'learn_module_id' => $this->module->id, 'itemable_type' => \App\Models\Learn\Assignment::class,
        ]);
        $this->assertDatabaseMissing('learn_rubrics', ['learn_assignment_id' => \App\Models\Learn\Assignment::first()->id]);
    }

    public function test_instructor_can_add_a_rubric_assignment_and_points_possible_is_ignored(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab Report', 'submission_type' => 'file', 'points_possible' => 999,
            'rubric_criteria' => [
                ['description' => 'Grammar', 'max_points' => 10],
                ['description' => 'Content', 'max_points' => 20],
            ],
        ])->assertRedirect();

        $assignment = \App\Models\Learn\Assignment::where('title', 'Lab Report')->firstOrFail();
        $this->assertNull($assignment->points_possible);
        $this->assertSame(30.0, $assignment->maxScore());
        $this->assertCount(2, $assignment->rubric->criteria);
    }

    public function test_stranger_cannot_add_an_assignment(): void
    {
        $this->actingAs($this->stranger)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Hack', 'submission_type' => 'text',
        ])->assertForbidden();
    }
}
