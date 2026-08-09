<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizGradingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private $essayQuestion;
    private User $teacher;
    private QuizAttempt $attempt;
    private QuizAttemptAnswer $essayAnswer;

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
        $this->quiz = Quiz::create(['title' => 'Quiz']);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $mcQuestion = $this->quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q1', 'points' => 5, 'position' => 0]);
        $correct = $mcQuestion->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $this->essayQuestion = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q2', 'points' => 10, 'position' => 1]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);

        $this->attempt = QuizAttempt::create([
            'learn_quiz_id' => $this->quiz->id, 'student_id' => $studentId, 'attempt_number' => 1,
            'question_order' => [$mcQuestion->id, $this->essayQuestion->id], 'started_at' => now()->subMinutes(5), 'submitted_at' => now(),
        ]);
        $mcAnswer = QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $this->attempt->id, 'learn_quiz_question_id' => $mcQuestion->id,
            'is_correct' => true, 'points_earned' => 5,
        ]);
        $mcAnswer->selectedOptions()->create(['learn_quiz_question_option_id' => $correct->id]);
        $this->essayAnswer = QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $this->attempt->id, 'learn_quiz_question_id' => $this->essayQuestion->id,
            'answer_text' => 'My essay response.',
        ]);
    }

    public function test_index_lists_attempts_with_pending_essay_count(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('learn.quizzes.attempts', $this->quiz));

        $response->assertInertia(fn ($page) => $page
            ->where('attempts.0.pending_essays', 1)
            ->where('attempts.0.score', null)
        );
    }

    public function test_grading_the_only_pending_essay_computes_the_attempt_score(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), [
            'points_earned' => 8,
        ])->assertRedirect();

        $this->assertSame('8.00', $this->essayAnswer->fresh()->points_earned);
        $this->assertNotNull($this->essayAnswer->fresh()->graded_at);
        $this->assertSame('13.00', $this->attempt->fresh()->score);
    }

    public function test_grading_rejects_a_score_above_the_question_max(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), [
            'points_earned' => 999,
        ])->assertSessionHasErrors('points_earned');
    }

    public function test_reopen_clears_grading_but_preserves_answer_content(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), ['points_earned' => 8]);

        $this->actingAs($this->teacher)->post(route('learn.quiz-attempts.reopen', $this->attempt))->assertRedirect();

        $this->assertNull($this->attempt->fresh()->submitted_at);
        $this->assertNull($this->attempt->fresh()->score);
        $this->assertNull($this->essayAnswer->fresh()->points_earned);
        $this->assertSame('My essay response.', $this->essayAnswer->fresh()->answer_text);
    }

    public function test_stranger_cannot_view_grade_or_reopen(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('learn.quizzes.attempts', $this->quiz))->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), ['points_earned' => 5])->assertForbidden();
        $this->actingAs($stranger)->post(route('learn.quiz-attempts.reopen', $this->attempt))->assertForbidden();
    }
}
