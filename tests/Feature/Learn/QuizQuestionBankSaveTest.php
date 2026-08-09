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
use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionBankSaveTest extends TestCase
{
    use RefreshDatabase;

    private $module;
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
        $this->module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_storing_a_quiz_saves_a_flagged_question_to_the_bank(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Quiz',
            'questions' => [
                [
                    'question_type' => 'multiple_choice', 'prompt' => 'Q?', 'points' => 5,
                    'options' => [['option_text' => 'A', 'is_correct' => true], ['option_text' => 'B', 'is_correct' => false]],
                    'save_to_bank' => true, 'bank_name' => 'Reusable MC',
                ],
            ],
        ])->assertRedirect();

        $bankItem = QuizQuestionBankItem::where('name', 'Reusable MC')->firstOrFail();
        $this->assertSame($this->teacher->id, $bankItem->user_id);
        $this->assertCount(2, $bankItem->options);
    }

    public function test_adding_a_question_to_an_existing_quiz_can_also_save_to_bank(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 0]);

        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $quiz), [
            'question_type' => 'short_answer', 'prompt' => 'Capital?', 'points' => 5,
            'accepted_answers' => ['Manila'],
            'save_to_bank' => true, 'bank_name' => 'Capital Question',
        ])->assertRedirect();

        $bankItem = QuizQuestionBankItem::where('name', 'Capital Question')->firstOrFail();
        $this->assertSame('short_answer', $bankItem->question_type);
        $this->assertSame('Manila', $bankItem->options->first()->option_text);
        $this->assertTrue($bankItem->options->first()->is_correct);
    }

    public function test_omitting_save_to_bank_creates_no_bank_item(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Quiz',
            'questions' => [['question_type' => 'essay', 'prompt' => 'Q?', 'points' => 5]],
        ]);

        $this->assertDatabaseCount('learn_quiz_question_bank_items', 0);
    }

    public function test_editing_the_live_question_afterward_never_touches_the_bank_copy(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Quiz',
            'questions' => [['question_type' => 'essay', 'prompt' => 'Original', 'points' => 5, 'save_to_bank' => true, 'bank_name' => 'B']],
        ]);

        $quiz = Quiz::where('title', 'Quiz')->firstOrFail();
        $question = $quiz->questions->first();
        $bankItem = QuizQuestionBankItem::where('name', 'B')->firstOrFail();

        $question->update(['prompt' => 'Changed']);
        $this->assertSame('Original', $bankItem->fresh()->prompt);

        $bankItem->update(['name' => 'Renamed']);
        $this->assertSame('Changed', $question->fresh()->prompt);
    }
}
