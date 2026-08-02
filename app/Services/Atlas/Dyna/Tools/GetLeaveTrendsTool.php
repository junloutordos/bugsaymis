<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\HR\LeaveApplication;
use App\Models\User;
use Illuminate\Support\Carbon;

class GetLeaveTrendsTool implements DynaTool
{
    public function name(): string
    {
        return 'get_leave_trends';
    }

    public function description(): string
    {
        return 'Returns leave application counts grouped by status (pending, forwarded, '
             . 'approved, rejected) for a given date range. Use for questions about leave '
             . 'volume, pending approvals, or leave trends over time.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['from_date', 'to_date'],
            'properties' => [
                'from_date' => ['type' => 'string', 'description' => 'Start date, format YYYY-MM-DD.'],
                'to_date' => ['type' => 'string', 'description' => 'End date, format YYYY-MM-DD.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = LeaveApplication::query()
            ->whereBetween('created_at', [
                Carbon::parse($input['from_date'])->startOfDay(),
                Carbon::parse($input['to_date'])->endOfDay(),
            ]);

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('user', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
