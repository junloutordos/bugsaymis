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

class QuizGradingRosterAnswersTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_includes_full_answer_detail_per_attempt(): void
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
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $question = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q?', 'points' => 5, 'position' => 0]);
        $correct = $question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);
        $attempt = QuizAttempt::create([
            'learn_quiz_id' => $quiz->id, 'student_id' => $studentId, 'attempt_number' => 1,
            'question_order' => [$question->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => 5,
        ]);
        $answer = QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $question->id,
            'is_correct' => true, 'points_earned' => 5,
        ]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $correct->id]);

        $response = $this->actingAs($teacher)->get(route('learn.quizzes.attempts', $quiz));

        $response->assertInertia(fn ($page) => $page
            ->where('attempts.0.answers.0.prompt', 'Q?')
            ->where('attempts.0.answers.0.options.0.is_correct', true)
            ->where('attempts.0.answers.0.selected_option_ids.0', $correct->id)
        );
    }
}
