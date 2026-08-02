<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\User;
use App\Services\Atlas\Dyna\DynaToolRegistry;
use App\Services\Atlas\Dyna\Tools\DynaTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
