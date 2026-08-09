<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizAttemptAnswerTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private $mcQuestion;
    private $optionA;
    private $optionB;
    private int $studentId;
    private QuizAttempt $attempt;

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
        $this->quiz = Quiz::create(['title' => 'Quiz', 'time_limit_minutes' => 10]);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $this->mcQuestion = $this->quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 5, 'position' => 0]);
        $this->optionA = $this->mcQuestion->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $this->optionB = $this->mcQuestion->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$this->studentId}"]);

        $this->attempt = QuizAttempt::create([
            'learn_quiz_id' => $this->quiz->id, 'student_id' => $this->studentId, 'attempt_number' => 1,
            'question_order' => [$this->mcQuestion->id], 'started_at' => now(),
        ]);
    }

    public function test_saving_an_answer_persists_the_selected_option(): void
    {
        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertRedirect();

        $answer = $this->attempt->answers()->first();
        $this->assertSame([$this->optionA->id], $answer->selectedOptions->pluck('learn_quiz_question_option_id')->all());
    }

    public function test_resaving_replaces_the_previous_selection(): void
    {
        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ]);
        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionB->id],
        ]);

        $answer = $this->attempt->answers()->first();
        $this->assertSame([$this->optionB->id], $answer->fresh()->selectedOptions->pluck('learn_quiz_question_option_id')->all());
    }

    public function test_answering_a_question_not_in_this_attempt_is_rejected(): void
    {
        $otherQuestion = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Not drawn', 'points' => 5, 'position' => 1]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $otherQuestion]), [
            'answer_text' => 'x',
        ])->assertNotFound();
    }

    public function test_answering_after_submission_is_rejected(): void
    {
        $this->attempt->update(['submitted_at' => now(), 'score' => 5]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertForbidden();
    }

    public function test_answering_past_the_time_limit_lazily_finalizes_and_rejects(): void
    {
        $this->attempt->update(['started_at' => now()->subMinutes(20)]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertForbidden();

        $this->assertNotNull($this->attempt->fresh()->submitted_at);
        $this->assertTrue($this->attempt->fresh()->auto_submitted);
    }

    public function test_a_different_student_cannot_answer_this_attempt(): void
    {
        $otherStudentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $otherStudentId, 'pisaysystemID' => "PS{$otherStudentId}", 'firstname' => 'Other', 'lastname' => 'Student',
        ]);
        session(['student_pisaysystemID' => "PS{$otherStudentId}"]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertForbidden();
    }
}
