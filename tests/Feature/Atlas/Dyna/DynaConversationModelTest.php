<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Atlas\DynaMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynaConversationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_has_ordered_messages_and_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id, 'title' => 'Leave trends']);

        $second = DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'second',
            'created_at' => now()->addMinute(),
        ]);
        $first = DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'first',
            'tool_calls' => [['name' => 'get_headcount', 'input' => [], 'result' => ['total_headcount' => 42]]],
            'created_at' => now(),
        ]);

        $this->assertTrue($conversation->user->is($user));
        $this->assertEquals(['first', 'second'], $conversation->fresh()->messages->pluck('content')->all());
        $this->assertIsArray($first->fresh()->tool_calls);
        $this->assertEquals('get_headcount', $first->fresh()->tool_calls[0]['name']);
        $this->assertTrue($second->conversation->is($conversation));
    }
}
