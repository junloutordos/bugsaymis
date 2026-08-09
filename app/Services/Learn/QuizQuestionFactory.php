<?php

namespace App\Services\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizQuestion;
use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;

class QuizQuestionFactory
{
    /** @param array{question_type: string, prompt: string, points: float, difficulty?: ?string, options?: array, accepted_answers?: array} $data */
    public function create(Quiz $quiz, array $data, int $position, ?User $user = null): QuizQuestion
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

        if ($user && ($data['save_to_bank'] ?? false)) {
            $this->saveToBank($user, $data);
        }

        return $question;
    }

    private function saveToBank(User $user, array $data): void
    {
        $bankItem = QuizQuestionBankItem::create([
            'user_id' => $user->id,
            'name' => $data['bank_name'],
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'points' => $data['points'],
            'difficulty' => $data['difficulty'] ?? null,
        ]);

        if (in_array($data['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            foreach (($data['options'] ?? []) as $optPosition => $option) {
                $bankItem->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'] ?? false,
                    'position' => $optPosition,
                ]);
            }
        } elseif ($data['question_type'] === 'short_answer') {
            foreach (($data['accepted_answers'] ?? []) as $optPosition => $answer) {
                $bankItem->options()->create([
                    'option_text' => $answer, 'is_correct' => true, 'position' => $optPosition,
                ]);
            }
        }
    }
}
