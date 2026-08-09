<?php

namespace App\Services\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizQuestion;

class QuizQuestionFactory
{
    /** @param array{question_type: string, prompt: string, points: float, difficulty?: ?string, options?: array, accepted_answers?: array} $data */
    public function create(Quiz $quiz, array $data, int $position): QuizQuestion
    {
        $question = $quiz->questions()->create([
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'points' => $data['points'],
            'position' => $position,
            'difficulty' => $data['difficulty'] ?? null,
        ]);

        if (in_array($data['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            foreach (($data['options'] ?? []) as $optPosition => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'] ?? false,
                    'position' => $optPosition,
                ]);
            }
        } elseif ($data['question_type'] === 'short_answer') {
            foreach (($data['accepted_answers'] ?? []) as $answer) {
                $question->acceptedAnswers()->create(['answer_text' => $answer]);
            }
        }

        return $question;
    }
}
