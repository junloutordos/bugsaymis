<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreAssignmentSavesRubricTemplateTest extends TestCase
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

    public function test_save_as_template_creates_an_independent_copy(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab', 'submission_type' => 'file',
            'rubric_criteria' => [
                ['description' => 'Accuracy', 'max_points' => 15],
                ['description' => 'Neatness', 'max_points' => 5],
            ],
            'save_as_template' => true, 'template_name' => 'Lab Rubric',
        ])->assertRedirect();

        $template = RubricTemplate::where('name', 'Lab Rubric')->firstOrFail();
        $this->assertSame($this->teacher->id, $template->user_id);
        $this->assertCount(2, $template->criteria);

        $assignment = Assignment::where('title', 'Lab')->firstOrFail();
        $this->assertCount(2, $assignment->rubric->criteria);
        // Two independent tables — the assignment's own rubric criteria row is never the
        // template's criterion row (different model classes/tables entirely).
        $this->assertInstanceOf(\App\Models\Learn\RubricCriterion::class, $assignment->rubric->criteria->first());
        $this->assertInstanceOf(\App\Models\Learn\RubricTemplateCriterion::class, $template->criteria->first());
    }

    public function test_omitting_save_as_template_creates_no_template(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab', 'submission_type' => 'file',
            'rubric_criteria' => [['description' => 'Accuracy', 'max_points' => 15]],
        ]);

        $this->assertDatabaseCount('learn_rubric_templates', 0);
    }

    public function test_editing_either_side_afterward_never_affects_the_other(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab', 'submission_type' => 'file',
            'rubric_criteria' => [['description' => 'Accuracy', 'max_points' => 15]],
            'save_as_template' => true, 'template_name' => 'Lab Rubric',
        ]);

        $assignment = Assignment::where('title', 'Lab')->firstOrFail();
        $template = RubricTemplate::where('name', 'Lab Rubric')->firstOrFail();

        $assignment->rubric->criteria->first()->update(['max_points' => 99]);
        $this->assertSame('15.00', $template->fresh()->criteria->first()->max_points);

        $template->update(['name' => 'Renamed']);
        $this->assertSame('Lab', $assignment->fresh()->title);
    }
}
