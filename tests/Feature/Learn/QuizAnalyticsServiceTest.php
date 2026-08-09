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
use App\Services\Learn\QuizAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuizAnalyticsService $service;
    private Course $course;
    private $module;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QuizAnalyticsService::class);

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
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $this->module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_item_analysis_computes_per_question_percentage_and_score_distribution(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 0]);
        $question = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 10, 'position' => 0, 'difficulty' => 'medium']);

        foreach ([10, 5, 0] as $score) {
            $attempt = QuizAttempt::create([
                'learn_quiz_id' => $quiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
                'question_order' => [$question->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => $score,
            ]);
            QuizAttemptAnswer::create([
                'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $question->id,
                'points_earned' => $score, 'is_correct' => $score === 10,
            ]);
        }

        $analysis = $this->service->itemAnalysis($quiz);

        $this->assertSame(50.0, $analysis['questions'][0]['avg_score_percentage']);
        $this->assertSame('medium', $analysis['questions'][0]['difficulty']);
        $this->assertSame(0.0, $analysis['distribution']['min']);
        $this->assertSame(10.0, $analysis['distribution']['max']);
        $this->assertSame(5.0, $analysis['distribution']['avg']);
        $this->assertSame(5.0, $analysis['distribution']['median']);
    }

    public function test_course_trend_orders_quizzes_by_due_date_and_computes_average_percentage(): void
    {
        $laterQuiz = Quiz::create(['title' => 'Later Quiz', 'due_at' => now()->addWeek()]);
        $laterQuiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 0]);
        $laterQuestion = $laterQuiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        QuizAttempt::create([
            'learn_quiz_id' => $laterQuiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
            'question_order' => [$laterQuestion->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => 10,
        ]);

        $earlierQuiz = Quiz::create(['title' => 'Earlier Quiz', 'due_at' => now()->subWeek()]);
        $earlierQuiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 1]);
        $earlierQuestion = $earlierQuiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        QuizAttempt::create([
            'learn_quiz_id' => $earlierQuiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
            'question_order' => [$earlierQuestion->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => 5,
        ]);

        $this->course->load('modules.items.itemable');
        $trend = $this->service->courseTrend($this->course);

        $this->assertSame('Earlier Quiz', $trend['quizzes'][0]['title']);
        $this->assertSame(50.0, $trend['quizzes'][0]['avg_score_percentage']);
        $this->assertSame('Later Quiz', $trend['quizzes'][1]['title']);
        $this->assertSame(100.0, $trend['quizzes'][1]['avg_score_percentage']);
    }
}
