<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\FacultyLoading\FacultyLoad;
use App\Models\User;

class GetFacultyLoadDistributionTool implements DynaTool
{
    public function name(): string { return 'get_faculty_load_distribution'; }

    public function description(): string
    {
        return 'Returns faculty teaching-load status distribution (underload/full_load/overload counts). '
             . 'Use for questions about faculty workload, overload counts, or load balancing.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(User $user, array $input): array
    {
        $query = FacultyLoad::query();

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('faculty', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('load_status, count(*) as total')
            ->groupBy('load_status')
            ->pluck('total', 'load_status')
            ->toArray();
    }
}
