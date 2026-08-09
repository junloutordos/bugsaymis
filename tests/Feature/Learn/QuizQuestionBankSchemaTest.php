<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuizQuestionBankSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_question_bank_items'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_bank_items', [
            'id', 'user_id', 'name', 'question_type', 'prompt', 'points', 'difficulty',
        ]));

        $this->assertTrue(Schema::hasTable('learn_quiz_question_bank_options'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_bank_options', [
            'id', 'learn_quiz_question_bank_item_id', 'option_text', 'is_correct', 'position',
        ]));
    }

    public function test_bank_item_has_ordered_options(): void
    {
        $user = User::factory()->create();
        $item = QuizQuestionBankItem::create([
            'user_id' => $user->id, 'name' => 'Photosynthesis MC', 'question_type' => 'multiple_choice',
            'prompt' => 'What do plants produce?', 'points' => 5,
        ]);
        $item->options()->create(['option_text' => 'Oxygen', 'is_correct' => true, 'position' => 1]);
        $item->options()->create(['option_text' => 'Nitrogen', 'is_correct' => false, 'position' => 0]);

        $this->assertSame(['Nitrogen', 'Oxygen'], $item->fresh()->options->pluck('option_text')->all());
    }
}
