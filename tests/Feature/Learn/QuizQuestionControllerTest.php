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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
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
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->quiz = Quiz::create(['title' => 'Quiz']);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
    }

    public function test_instructor_can_add_edit_and_delete_a_question_before_locking(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'true_false', 'prompt' => 'The sky is blue.', 'points' => 5,
            'options' => [
                ['option_text' => 'True', 'is_correct' => true],
                ['option_text' => 'False', 'is_correct' => false],
            ],
        ])->assertRedirect();

        $question = $this->quiz->fresh()->questions()->where('prompt', 'The sky is blue.')->firstOrFail();
        $this->assertCount(2, $question->options);

        $this->actingAs($this->teacher)->put(route('learn.quiz-questions.update', $question), [
            'question_type' => 'true_false', 'prompt' => 'The sky is green.', 'points' => 8,
            'options' => [
                ['option_text' => 'True', 'is_correct' => false],
                ['option_text' => 'False', 'is_correct' => true],
            ],
        ])->assertRedirect();
        $question->refresh();
        $this->assertSame('The sky is green.', $question->prompt);
        $this->assertSame('8.00', $question->points);
        $this->assertCount(2, $question->options);
        $this->assertTrue($question->options->firstWhere('option_text', 'False')->is_correct);

        $this->actingAs($this->teacher)->delete(route('learn.quiz-questions.destroy', $question))->assertRedirect();
        $this->assertDatabaseMissing('learn_quiz_questions', ['id' => $question->id]);
    }

    public function test_locked_quiz_rejects_editing_or_deleting_but_allows_adding(): void
    {
        $question = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 5, 'position' => 0]);
        $this->quiz->update(['is_locked' => true]);

        $this->actingAs($this->teacher)->put(route('learn.quiz-questions.update', $question), [
            'question_type' => 'essay', 'prompt' => 'Changed', 'points' => 5,
        ])->assertForbidden();

        $this->actingAs($this->teacher)->delete(route('learn.quiz-questions.destroy', $question))->assertForbidden();

        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'essay', 'prompt' => 'New question', 'points' => 5,
        ])->assertRedirect();

        $this->assertSame('A', $question->fresh()->prompt);
        $this->assertTrue($this->quiz->fresh()->questions()->where('prompt', 'New question')->exists());
    }

    public function test_questions_to_draw_rejects_a_mismatched_point_value(): void
    {
        $this->quiz->update(['questions_to_draw' => 2]);
        $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 5, 'position' => 0]);

        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'essay', 'prompt' => 'B', 'points' => 9,
        ])->assertSessionHasErrors('points');

        $this->assertFalse($this->quiz->fresh()->questions()->where('prompt', 'B')->exists());
    }

    public function test_stranger_cannot_manage_questions(): void
    {
        $stranger = User::factory()->create();
        $question = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 5, 'position' => 0]);

        $this->actingAs($stranger)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'essay', 'prompt' => 'X', 'points' => 5,
        ])->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.quiz-questions.update', $question), [
            'question_type' => 'essay', 'prompt' => 'X', 'points' => 5,
        ])->assertForbidden();
        $this->actingAs($stranger)->delete(route('learn.quiz-questions.destroy', $question))->assertForbidden();
    }
}
