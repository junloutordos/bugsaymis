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
use App\Models\Learn\ModuleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleItemDiscussionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $module;
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
        $this->module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_instructor_can_add_a_graded_discussion(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('learn.items.store-discussion', $this->module), [
            'title' => 'Week 1 Discussion', 'prompt' => 'Discuss chapter 1.', 'points_possible' => 10,
        ]);

        $response->assertRedirect();

        $discussion = Discussion::where('title', 'Week 1 Discussion')->firstOrFail();
        $this->assertSame('Discuss chapter 1.', $discussion->prompt);
        $this->assertSame(10.0, $discussion->maxScore());

        $item = ModuleItem::where('itemable_type', Discussion::class)->where('itemable_id', $discussion->id)->first();
        $this->assertNotNull($item);
    }

    public function test_instructor_can_add_an_ungraded_discussion(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-discussion', $this->module), [
            'title' => 'Open Forum', 'prompt' => 'Anything goes.',
        ])->assertRedirect();

        $discussion = Discussion::where('title', 'Open Forum')->firstOrFail();
        $this->assertNull($discussion->maxScore());
    }

    public function test_stranger_cannot_add_a_discussion(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('learn.items.store-discussion', $this->module), [
            'title' => 'X', 'prompt' => 'Y',
        ])->assertForbidden();
    }
}
