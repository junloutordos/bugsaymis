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
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignmentGradingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;
    private int $studentId;

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

        $this->studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS000000201', 'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
    }

    private function makeFlatAssignment(): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        return $assignment;
    }

    private function makeRubricAssignment(): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 2', 'position' => 1]);
        $assignment = Assignment::create(['title' => 'Lab', 'submission_type' => 'text']);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $rubric = $assignment->rubric()->create([]);
        $rubric->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);
        $rubric->criteria()->create(['description' => 'Content', 'max_points' => 20, 'position' => 1]);

        return $assignment->fresh();
    }

    public function test_index_shows_roster_and_403s_for_non_instructor(): void
    {
        $assignment = $this->makeFlatAssignment();

        $response = $this->actingAs($this->teacher)->get(route('learn.assignments.submissions', $assignment));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Grading')
            ->has('roster', 1)
            ->where('roster.0.status', 'not_submitted')
        );

        $this->actingAs($this->stranger)->get(route('learn.assignments.submissions', $assignment))->assertForbidden();
    }

    public function test_instructor_can_grade_a_flat_points_submission(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.submissions.grade', $submission), ['score' => 35, 'feedback_comment' => 'Good work'])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('35.00', $submission->score);
        $this->assertSame('Good work', $submission->feedback_comment);
        $this->assertNotNull($submission->graded_at);
        $this->assertSame($this->teacher->id, $submission->graded_by);
    }

    public function test_flat_points_score_cannot_exceed_points_possible(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.submissions.grade', $submission), ['score' => 999])
            ->assertSessionHasErrors('score');
    }

    public function test_instructor_can_grade_a_rubric_submission_and_score_is_the_sum(): void
    {
        $assignment = $this->makeRubricAssignment();
        $criteria = $assignment->rubric->criteria;
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My lab', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->teacher)->put(route('learn.submissions.grade', $submission), [
            'rubric_scores' => [
                $criteria[0]->id => 8,
                $criteria[1]->id => 18,
            ],
            'feedback_comment' => 'Nice',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame('26.00', $submission->score);
        $this->assertCount(2, $submission->rubricScores);
    }

    public function test_reopen_clears_grade_and_unlocks_the_submission(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
            'score' => 30, 'graded_at' => now(), 'graded_by' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher)->post(route('learn.submissions.reopen', $submission))->assertRedirect();

        $submission->refresh();
        $this->assertNull($submission->score);
        $this->assertNull($submission->graded_at);
        $this->assertNull($submission->graded_by);
    }

    public function test_stranger_cannot_grade_or_reopen(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->stranger)
            ->put(route('learn.submissions.grade', $submission), ['score' => 10])
            ->assertForbidden();
        $this->actingAs($this->stranger)
            ->post(route('learn.submissions.reopen', $submission))
            ->assertForbidden();
    }
}
