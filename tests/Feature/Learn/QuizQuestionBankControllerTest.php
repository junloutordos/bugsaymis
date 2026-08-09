<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionBankControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_and_delete_a_bank_item(): void
    {
        $user = User::factory()->create();
        $item = QuizQuestionBankItem::create([
            'user_id' => $user->id, 'name' => 'Original', 'question_type' => 'essay', 'prompt' => 'P', 'points' => 5,
        ]);

        $this->actingAs($user)
            ->put(route('learn.quiz-question-bank.update', $item), ['name' => 'Renamed'])
            ->assertRedirect();
        $this->assertSame('Renamed', $item->fresh()->name);

        $this->actingAs($user)
            ->delete(route('learn.quiz-question-bank.destroy', $item))
            ->assertRedirect();
        $this->assertDatabaseMissing('learn_quiz_question_bank_items', ['id' => $item->id]);
    }

    public function test_non_owner_cannot_rename_or_delete(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $item = QuizQuestionBankItem::create([
            'user_id' => $owner->id, 'name' => 'Original', 'question_type' => 'essay', 'prompt' => 'P', 'points' => 5,
        ]);

        $this->actingAs($stranger)
            ->put(route('learn.quiz-question-bank.update', $item), ['name' => 'Hacked'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->delete(route('learn.quiz-question-bank.destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('learn_quiz_question_bank_items', ['id' => $item->id, 'name' => 'Original']);
    }
}
