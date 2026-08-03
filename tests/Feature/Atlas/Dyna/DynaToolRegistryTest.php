<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\User;
use App\Services\Atlas\Dyna\DynaToolRegistry;
use App\Services\Atlas\Dyna\Tools\DynaTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DynaToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_builds_bedrock_tool_config_from_registered_tools(): void
    {
        $tool = new class implements DynaTool {
            public function name(): string { return 'ping'; }
            public function description(): string { return 'Returns pong.'; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function execute(User $user, array $input): array { return ['pong' => true]; }
        };

        $registry = new DynaToolRegistry([$tool]);

        $this->assertEquals([
            'tools' => [[
                'toolSpec' => [
                    'name' => 'ping',
                    'description' => 'Returns pong.',
                    'inputSchema' => ['json' => ['type' => 'object', 'properties' => []]],
                ],
            ]],
        ], $registry->toBedrockToolConfig());

        $this->assertEquals(['pong' => true], $registry->execute('ping', [], User::factory()->make()));
    }

    public function test_registry_rejects_an_unknown_tool_name(): void
    {
        $registry = new DynaToolRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $registry->execute('does_not_exist', [], User::factory()->make());
    }

    public function test_registry_includes_the_new_full_profile_and_schedule_tools(): void
    {
        $registry = app(DynaToolRegistry::class);
        $config = $registry->toBedrockToolConfig();
        $names = collect($config['tools'])->pluck('toolSpec.name')->all();

        $this->assertContains('get_class_schedule', $names);
        $this->assertContains('get_employee_full_profile', $names);
        $this->assertContains('get_student_full_profile', $names);
        $this->assertCount(25, $names);
    }

    public function test_registry_catches_a_tool_exception_logs_it_and_returns_a_structured_error(): void
    {
        Log::spy();

        $tool = new class implements DynaTool {
            public function name(): string { return 'broken_tool'; }
            public function description(): string { return 'desc'; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function execute(User $user, array $input): array { throw new \RuntimeException('DB timeout'); }
        };
        $registry = new DynaToolRegistry([$tool]);

        $result = $registry->execute('broken_tool', [], User::factory()->make());

        $this->assertEquals(['error' => 'This data could not be retrieved right now.'], $result);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($message, $context) => $message === 'Dyna tool execution failed' && $context['tool'] === 'broken_tool'
        );
    }
}
