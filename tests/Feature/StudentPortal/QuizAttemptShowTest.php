<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizAttemptShowTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private $question;
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
        $this->quiz = Quiz::create(['title' => 'Quiz', 'shuffle_options' => true]);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $this->question = $this->quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 5, 'position' => 0]);
        $this->question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $this->question->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);
        $this->question->options()->create(['option_text' => 'C', 'is_correct' => false, 'position' => 2]);
        $this->question->options()->create(['option_text' => 'D', 'is_correct' => false, 'position' => 3]);

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
            'question_order' => [$this->question->id], 'started_at' => now(),
        ]);
    }

    public function test_option_order_is_a_reproducible_shuffle_across_two_loads(): void
    {
        $first = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));
        $second = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));

        $extractOrder = fn ($response) => collect($response->original->getData()['page']['props']['attempt']['questions'])
            ->first()['options'];

        $orderA = collect($extractOrder($first))->pluck('id')->all();
        $orderB = collect($extractOrder($second))->pluck('id')->all();

        $this->assertSame($orderA, $orderB);
        $this->assertEqualsCanonicalizing($this->question->options->pluck('id')->all(), $orderA);
    }

    public function test_answer_correctness_is_hidden_while_in_progress_and_shown_after_submission(): void
    {
        $option = $this->question->options()->where('is_correct', true)->first();
        QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $this->attempt->id, 'learn_quiz_question_id' => $this->question->id])
            ->selectedOptions()->create(['learn_quiz_question_option_id' => $option->id]);

        $inProgress = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));
        $inProgress->assertInertia(fn ($page) => $page->where('attempt.questions.0.your_answer.is_correct', null));

        $this->attempt->update(['submitted_at' => now(), 'score' => 5]);
        $this->attempt->answers()->first()->update(['is_correct' => true, 'points_earned' => 5]);

        $submitted = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));
        $submitted->assertInertia(fn ($page) => $page->where('attempt.questions.0.your_answer.is_correct', true));
    }

    public function test_a_different_student_cannot_view_this_attempt(): void
    {
        $otherStudentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $otherStudentId, 'pisaysystemID' => "PS{$otherStudentId}", 'firstname' => 'Other', 'lastname' => 'Student',
        ]);
        session(['student_pisaysystemID' => "PS{$otherStudentId}"]);

        $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt))->assertForbidden();
    }
}
