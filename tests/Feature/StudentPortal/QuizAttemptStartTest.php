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

class QuizAttemptStartTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
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
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $this->quiz = Quiz::create(['title' => 'Quiz', 'max_attempts' => 1, 'questions_to_draw' => 2]);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        foreach (range(1, 4) as $i) {
            $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => "Q{$i}", 'points' => 5, 'position' => $i]);
        }

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$this->studentId}"]);
    }

    public function test_starting_creates_an_attempt_with_a_sampled_question_order(): void
    {
        $response = $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $response->assertRedirect();

        $attempt = QuizAttempt::where('learn_quiz_id', $this->quiz->id)->where('student_id', $this->studentId)->firstOrFail();
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertCount(2, $attempt->question_order);
        $this->assertNull($attempt->submitted_at);

        $allQuestionIds = $this->quiz->questions->pluck('id')->all();
        foreach ($attempt->question_order as $id) {
            $this->assertContains($id, $allQuestionIds);
        }
    }

    public function test_starting_again_while_in_progress_resumes_the_same_attempt(): void
    {
        $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $first = QuizAttempt::where('learn_quiz_id', $this->quiz->id)->first();

        $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $this->assertSame(1, QuizAttempt::where('learn_quiz_id', $this->quiz->id)->count());
        $this->assertSame($first->id, QuizAttempt::where('learn_quiz_id', $this->quiz->id)->first()->id);
    }

    public function test_max_attempts_is_enforced_once_a_prior_attempt_is_submitted(): void
    {
        QuizAttempt::create([
            'learn_quiz_id' => $this->quiz->id, 'student_id' => $this->studentId, 'attempt_number' => 1,
            'question_order' => [], 'started_at' => now()->subHour(), 'submitted_at' => now(), 'score' => 10,
        ]);

        $response = $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $response->assertSessionHasErrors('quiz');
        $this->assertSame(1, QuizAttempt::where('learn_quiz_id', $this->quiz->id)->count());
    }
}
