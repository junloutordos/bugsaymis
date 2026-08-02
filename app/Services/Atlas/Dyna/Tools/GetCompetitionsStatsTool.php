<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\CID\Competition;
use App\Models\User;

class GetCompetitionsStatsTool implements DynaTool
{
    public function name(): string { return 'get_competitions_stats'; }

    public function description(): string
    {
        return 'Returns competition counts by level (campus/inter_campus/regional/national/international). '
             . 'Use for questions about competition participation or how many competitions the school entered.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'school_year_id' => ['type' => 'integer', 'description' => 'Optional school year ID to filter to.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = Competition::query();

        if (! empty($input['school_year_id'])) {
            $query->where('school_year_id', $input['school_year_id']);
        }

        return $query->selectRaw('level, count(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();
    }
}
