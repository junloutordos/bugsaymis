<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_has_ordered_questions_with_options_and_accepted_answers(): void
    {
        $quiz = Quiz::create(['title' => 'Chapter 1 Quiz']);

        $q1 = $quiz->questions()->create([
            'question_type' => 'multiple_choice', 'prompt' => 'What is 2+2?', 'points' => 5, 'position' => 1,
        ]);
        $q1->options()->create(['option_text' => '4', 'is_correct' => true, 'position' => 0]);
        $q1->options()->create(['option_text' => '5', 'is_correct' => false, 'position' => 1]);

        $q0 = $quiz->questions()->create([
            'question_type' => 'short_answer', 'prompt' => 'Capital of the Philippines?', 'points' => 5, 'position' => 0,
        ]);
        $q0->acceptedAnswers()->create(['answer_text' => 'Manila']);

        $this->assertSame([$q0->id, $q1->id], $quiz->fresh()->questions->pluck('id')->all());
        $this->assertCount(2, $q1->fresh()->options);
        $this->assertCount(1, $q0->fresh()->acceptedAnswers);
    }

    public function test_quiz_max_score_sums_question_points_when_not_using_draw(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 10, 'position' => 0]);
        $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'B', 'points' => 15, 'position' => 1]);

        $this->assertSame(25.0, $quiz->maxScore());
    }

    public function test_quiz_max_score_uses_draw_count_times_per_question_points_when_drawing(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'questions_to_draw' => 3]);
        foreach (range(1, 5) as $i) {
            $quiz->questions()->create(['question_type' => 'essay', 'prompt' => "Q{$i}", 'points' => 4, 'position' => $i]);
        }

        $this->assertSame(12.0, $quiz->maxScore());
    }
}
