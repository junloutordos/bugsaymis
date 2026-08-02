<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\User;

class GetHeadcountTool implements DynaTool
{
    public function name(): string
    {
        return 'get_headcount';
    }

    public function description(): string
    {
        return 'Returns active employee headcount, optionally broken down by division. '
             . 'Use for questions about staff/faculty counts or division size.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_by_division' => [
                    'type' => 'boolean',
                    'description' => 'If true, break the headcount down by division name.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = User::employees()->where('status', '<>', 'inactive');

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->where('division_id', $user->division_id);
        }

        if (! empty($input['group_by_division'])) {
            return $query->with('division')
                ->get()
                ->groupBy(fn (User $u) => $u->division?->division_name ?? 'Unassigned')
                ->map->count()
                ->toArray();
        }

        return ['total_headcount' => $query->count()];
    }
}
