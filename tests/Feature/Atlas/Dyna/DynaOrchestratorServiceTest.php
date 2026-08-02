<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\User;
use App\Services\Atlas\Dyna\DynaBedrockClientFactory;
use App\Services\Atlas\Dyna\DynaOrchestratorService;
use App\Services\Atlas\Dyna\DynaToolRegistry;
use App\Services\Atlas\Dyna\Tools\DynaTool;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynaOrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_executes_a_requested_tool_then_returns_the_models_final_answer(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id]);

        $tool = new class implements DynaTool {
            public function name(): string { return 'get_headcount'; }
            public function description(): string { return 'desc'; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function execute(User $user, array $input): array { return ['total_headcount' => 42]; }
        };
        $registry = new DynaToolRegistry([$tool]);

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturn(new Result([
                'output' => ['message' => ['role' => 'assistant', 'content' => [
                    ['toolUse' => ['toolUseId' => 'tu_1', 'name' => 'get_headcount', 'input' => []]],
                ]]],
                'stopReason' => 'tool_use',
            ]));
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturn(new Result([
                'output' => ['message' => ['role' => 'assistant', 'content' => [
                    ['text' => 'There are 42 active employees.'],
                ]]],
                'stopReason' => 'end_turn',
            ]));

        $clientFactory = Mockery::mock(DynaBedrockClientFactory::class);
        $clientFactory->shouldReceive('make')->andReturn($bedrock);

        $orchestrator = new DynaOrchestratorService($registry, $clientFactory);

        $answer = $orchestrator->reply($user, $conversation, 'How many active employees do we have?');

        $this->assertEquals('There are 42 active employees.', $answer);
        $this->assertCount(2, $conversation->fresh()->messages);
        $assistantMessage = $conversation->fresh()->messages->last();
        $this->assertEquals('get_headcount', $assistantMessage->tool_calls[0]['name']);
        $this->assertEquals(['total_headcount' => 42], $assistantMessage->tool_calls[0]['result']);
    }

    public function test_reply_strips_thinking_tags_amazon_nova_inlines_into_its_text_output(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id]);
        $registry = new DynaToolRegistry([]);

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturn(new Result([
                'output' => ['message' => ['role' => 'assistant', 'content' => [
                    ['text' => "<thinking> The user says hello, I should greet them back. </thinking>\nHello! How can I help?"],
                ]]],
                'stopReason' => 'end_turn',
            ]));

        $clientFactory = Mockery::mock(DynaBedrockClientFactory::class);
        $clientFactory->shouldReceive('make')->andReturn($bedrock);

        $orchestrator = new DynaOrchestratorService($registry, $clientFactory);

        $answer = $orchestrator->reply($user, $conversation, 'Hello');

        $this->assertEquals('Hello! How can I help?', $answer);
        $this->assertEquals('Hello! How can I help?', $conversation->fresh()->messages->last()->content);
    }

    public function test_reply_strips_an_unclosed_thinking_tag_left_by_a_truncated_response(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id]);
        $registry = new DynaToolRegistry([]);

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturn(new Result([
                'output' => ['message' => ['role' => 'assistant', 'content' => [
                    ['text' => '<thinking> The user is asking about headcount, let me figure out which'],
                ]]],
                'stopReason' => 'max_tokens',
            ]));

        $clientFactory = Mockery::mock(DynaBedrockClientFactory::class);
        $clientFactory->shouldReceive('make')->andReturn($bedrock);

        $orchestrator = new DynaOrchestratorService($registry, $clientFactory);

        $answer = $orchestrator->reply($user, $conversation, 'How many teaching staff?');

        $this->assertStringNotContainsString('<thinking>', $answer);
        $this->assertStringNotContainsString('let me figure out which', $answer);
        $this->assertNotEmpty($answer);
    }
}
