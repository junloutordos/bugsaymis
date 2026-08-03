<?php

namespace App\Services\Atlas\Dyna;

use App\Models\User;
use App\Services\Atlas\Dyna\Tools\DynaTool;
use Illuminate\Support\Facades\Log;

class DynaToolRegistry
{
    /** @var array<string, DynaTool> */
    private array $tools;

    /** @param DynaTool[] $tools */
    public function __construct(array $tools)
    {
        $this->tools = collect($tools)->keyBy(fn (DynaTool $t) => $t->name())->all();
    }

    public function toBedrockToolConfig(): array
    {
        return [
            'tools' => collect($this->tools)->values()->map(fn (DynaTool $t) => [
                'toolSpec' => [
                    'name' => $t->name(),
                    'description' => $t->description(),
                    'inputSchema' => ['json' => $t->inputSchema()],
                ],
            ])->all(),
        ];
    }

    public function execute(string $name, array $input, User $user): array
    {
        if (! isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Unknown Dyna tool: {$name}");
        }

        try {
            return $this->tools[$name]->execute($user, $input);
        } catch (\Throwable $e) {
            // A tool exception must never crash the whole chat turn — it's caught here so
            // Bedrock gets a valid tool result and the model can tell the user plainly that
            // this specific piece of data couldn't be retrieved, instead of the request
            // 500ing with no answer at all. Logged so failures are visible in CloudWatch
            // proactively rather than only being found after a user complaint.
            Log::error('Dyna tool execution failed', [
                'tool' => $name,
                'input' => $input,
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return ['error' => 'This data could not be retrieved right now.'];
        }
    }
}
