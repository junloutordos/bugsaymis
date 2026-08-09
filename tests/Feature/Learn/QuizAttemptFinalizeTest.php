<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Services\Learn\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptFinalizeTest extends TestCase
{
    use RefreshDatabase;

    private QuizAttemptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QuizAttemptService::class);
    }

    private function makeAttempt(Quiz $quiz, array $questionIds, ?\Illuminate\Support\Carbon $startedAt = null): QuizAttempt
    {
        return QuizAttempt::create([
            'learn_quiz_id' => $quiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
            'question_order' => $questionIds, 'started_at' => $startedAt ?? now(),
        ]);
    }

    public function test_single_select_grades_correct_and_incorrect(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $correct = $q->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $wrong = $q->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $answer = QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $correct->id]);

        $this->service->submit($attempt);

        $this->assertTrue($answer->fresh()->is_correct);
        $this->assertSame('10.00', $answer->fresh()->points_earned);
        $this->assertSame('10.00', $attempt->fresh()->score);
    }

    public function test_multi_select_awards_proportional_partial_credit_with_negative_floor(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'multiple_select', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $c1 = $q->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $c2 = $q->options()->create(['option_text' => 'B', 'is_correct' => true, 'position' => 1]);
        $wrong = $q->options()->create(['option_text' => 'C', 'is_correct' => false, 'position' => 2]);

        // Selects both correct + the one wrong option: (2 correct - 1 incorrect) / 2 total correct = 0.5 -> 5.00
        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $answer = QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $c1->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $c2->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $wrong->id]);

        $this->service->submit($attempt);

        $this->assertFalse($answer->fresh()->is_correct);
        $this->assertSame('5.00', $answer->fresh()->points_earned);
    }

    public function test_multi_select_floors_at_zero_when_wrong_selections_outweigh_correct(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'multiple_select', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $c1 = $q->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $w1 = $q->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);
        $w2 = $q->options()->create(['option_text' => 'C', 'is_correct' => false, 'position' => 2]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $answer = QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $w1->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $w2->id]);

        $this->service->submit($attempt);

        $this->assertSame('0.00', $answer->fresh()->points_earned);
    }

    public function test_short_answer_matches_case_insensitively_and_trimmed(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'short_answer', 'prompt' => 'Q', 'points' => 5, 'position' => 0]);
        $q->acceptedAnswers()->create(['answer_text' => 'Manila']);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id, 'answer_text' => '  manila  ',
        ]);

        $this->service->submit($attempt);

        $answer = $attempt->answers()->first();
        $this->assertTrue($answer->is_correct);
        $this->assertSame('5.00', $answer->points_earned);
    }

    public function test_essay_leaves_attempt_score_null_until_manually_graded(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id, 'answer_text' => 'My essay.',
        ]);

        $this->service->submit($attempt);

        $this->assertNotNull($attempt->fresh()->submitted_at);
        $this->assertNull($attempt->fresh()->score);
        $answer = $attempt->answers()->first();
        $this->assertNull($answer->is_correct);
        $this->assertNull($answer->points_earned);
    }

    public function test_submit_locks_the_quiz_on_first_submission_only(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $this->assertFalse($quiz->fresh()->is_locked);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $this->service->submit($attempt);

        $this->assertTrue($quiz->fresh()->is_locked);
    }

    public function test_lazy_finalize_backfills_submitted_at_to_the_deadline_and_marks_auto_submitted(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'time_limit_minutes' => 10]);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $startedAt = now()->subMinutes(30);
        $attempt = $this->makeAttempt($quiz, [$q->id], $startedAt);

        $result = $this->service->finalizeIfExpired($attempt);

        $this->assertTrue($result->auto_submitted);
        $this->assertEqualsWithDelta($startedAt->copy()->addMinutes(10)->timestamp, $result->submitted_at->timestamp, 1);
    }

    public function test_finalize_if_expired_is_a_no_op_before_the_deadline(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'time_limit_minutes' => 60]);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $attempt = $this->makeAttempt($quiz, [$q->id], now());

        $result = $this->service->finalizeIfExpired($attempt);

        $this->assertNull($result->submitted_at);
    }

    public function test_submit_is_idempotent_once_already_submitted(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $this->service->submit($attempt);
        $firstSubmittedAt = $attempt->fresh()->submitted_at;

        $this->service->submit($attempt->fresh());

        $this->assertEquals($firstSubmittedAt, $attempt->fresh()->submitted_at);
    }
}
