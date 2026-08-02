<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Registrar\StudentEnrollment;
use App\Models\User;

class GetEnrollmentStatusBreakdownTool implements DynaTool
{
    public function name(): string { return 'get_enrollment_status_breakdown'; }

    public function description(): string
    {
        return 'Returns student enrollment status counts (enrolled/dropped/transferred_out/on_leave/completed). '
             . 'Use for questions about enrollment numbers, drop rate, or transfer rate — deeper than the basic enrolled-only count in academics stats.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'school_year_id' => ['type' => 'integer', 'description' => 'Optional school year ID to filter to. Omit for the current school year across all records.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = StudentEnrollment::query();

        if (! empty($input['school_year_id'])) {
            $query->where('school_year_id', $input['school_year_id']);
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
