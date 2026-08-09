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

class LearnQuizSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_quiz_item_with_attempt_summary_and_no_answer_content(): void
    {
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
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id,
            'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $quiz = Quiz::create(['title' => 'Quiz', 'max_attempts' => 2]);
        $item = $quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $question = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q?', 'points' => 5, 'position' => 0]);
        $question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        QuizAttempt::create([
            'learn_quiz_id' => $quiz->id, 'student_id' => $studentId, 'attempt_number' => 1,
            'question_order' => [$question->id], 'started_at' => now()->subMinutes(10),
            'submitted_at' => now(), 'score' => 5,
        ]);

        session(['student_pisaysystemID' => "PS{$studentId}"]);

        $response = $this->get(route('student-portal.learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'quiz')
            ->where('course.modules.0.items.0.quiz.attempts_used', 1)
            ->where('course.modules.0.items.0.quiz.best_score', 5)
            ->where('course.modules.0.items.0.quiz.can_start_new_attempt', true)
            ->missing('course.modules.0.items.0.quiz.questions')
        );
    }
}
