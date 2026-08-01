<?php

namespace App\Services\Atlas\Dyna;

use App\Models\User;
use App\Services\Atlas\Dyna\Tools\DynaTool;

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

        return $this->tools[$name]->execute($user, $input);
    }
}
