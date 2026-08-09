<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnQuizSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_quizzes_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quizzes'));
        $this->assertTrue(Schema::hasColumns('learn_quizzes', [
            'id', 'title', 'instructions', 'time_limit_minutes', 'max_attempts',
            'questions_to_draw', 'shuffle_questions', 'shuffle_options', 'due_at',
            'is_locked', 'class_record_assessment_id', 'pushed_at',
        ]));
    }

    public function test_learn_quiz_questions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_questions'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_questions', [
            'id', 'learn_quiz_id', 'question_type', 'prompt', 'points', 'position', 'difficulty',
        ]));
    }

    public function test_learn_quiz_question_options_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_question_options'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_options', [
            'id', 'learn_quiz_question_id', 'option_text', 'is_correct', 'position',
        ]));
    }

    public function test_learn_quiz_question_accepted_answers_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_question_accepted_answers'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_accepted_answers', [
            'id', 'learn_quiz_question_id', 'answer_text',
        ]));
    }

    public function test_learn_quiz_attempts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_attempts'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_attempts', [
            'id', 'learn_quiz_id', 'student_id', 'attempt_number', 'question_order',
            'started_at', 'submitted_at', 'auto_submitted', 'score',
        ]));
    }

    public function test_learn_quiz_attempt_answers_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_attempt_answers'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_attempt_answers', [
            'id', 'learn_quiz_attempt_id', 'learn_quiz_question_id', 'answer_text',
            'is_correct', 'points_earned', 'graded_at', 'graded_by',
        ]));
    }

    public function test_learn_quiz_attempt_selected_options_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_attempt_selected_options'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_attempt_selected_options', [
            'id', 'learn_quiz_attempt_answer_id', 'learn_quiz_question_option_id',
        ]));
    }
}
