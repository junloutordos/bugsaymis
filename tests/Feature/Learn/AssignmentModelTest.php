<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Module;
use App\Models\Learn\RubricScore;
use App\Models\Learn\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourseWithModule(): Module
    {
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
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);

        return $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    private function attachAssignment(Module $module, array $attributes = []): Assignment
    {
        $assignment = Assignment::create(array_merge([
            'title' => 'Essay', 'submission_type' => 'text',
        ], $attributes));
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        return $assignment;
    }

    public function test_assignment_max_score_uses_points_possible_when_no_rubric(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module, ['points_possible' => 50]);

        $this->assertSame(50.0, $assignment->maxScore());
    }

    public function test_assignment_max_score_sums_rubric_criteria_when_rubric_exists(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module, ['points_possible' => 50]);
        $rubric = $assignment->rubric()->create([]);
        $rubric->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);
        $rubric->criteria()->create(['description' => 'Content', 'max_points' => 20, 'position' => 1]);

        $this->assertSame(30.0, $assignment->fresh()->maxScore());
    }

    public function test_assignment_max_score_is_null_when_neither_set(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $this->assertNull($assignment->maxScore());
    }

    public function test_assignment_course_resolves_through_module_item(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $this->assertTrue($assignment->course()->is($module->course));
    }

    public function test_assignment_can_edit_delegates_to_course_can_edit(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);
        $stranger = User::factory()->create();

        $this->assertFalse($assignment->canEdit($stranger));
    }

    public function test_submission_is_late_compares_submitted_at_to_assignment_due_at(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module, ['due_at' => '2026-01-10 23:59:00']);

        $onTime = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1,
            'submitted_at' => '2026-01-10 12:00:00',
        ]);
        $late = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 2,
            'submitted_at' => '2026-01-11 08:00:00',
        ]);

        $this->assertFalse($onTime->isLate());
        $this->assertTrue($late->isLate());
    }

    public function test_submission_is_late_false_when_assignment_has_no_due_date(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1,
            'submitted_at' => now(),
        ]);

        $this->assertFalse($submission->isLate());
    }

    public function test_submission_is_graded_reflects_graded_at(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1, 'submitted_at' => now(),
        ]);

        $this->assertFalse($submission->isGraded());
        $submission->update(['graded_at' => now(), 'score' => 90]);
        $this->assertTrue($submission->fresh()->isGraded());
    }

    public function test_rubric_score_belongs_to_submission_and_criterion(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);
        $rubric = $assignment->rubric()->create([]);
        $criterion = $rubric->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1, 'submitted_at' => now(),
        ]);

        $score = RubricScore::create([
            'learn_submission_id' => $submission->id, 'learn_rubric_criterion_id' => $criterion->id, 'points_earned' => 8,
        ]);

        $this->assertTrue($score->submission->is($submission));
        $this->assertTrue($score->criterion->is($criterion));
        $this->assertCount(1, $submission->rubricScores);
    }
}
