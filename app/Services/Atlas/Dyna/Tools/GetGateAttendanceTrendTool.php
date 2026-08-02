<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class GetGateAttendanceTrendTool implements DynaTool
{
    public function name(): string { return 'get_gate_attendance_trend'; }

    public function description(): string
    {
        return 'Returns daily gate-scan counts for a date range. '
             . 'Use for questions about student attendance trends over multiple days — for today only, use academics stats instead.';
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
        return StudentAttendanceLog::query()
            ->whereBetween('scan_time', [
                Carbon::parse($input['from_date'])->startOfDay(),
                Carbon::parse($input['to_date'])->endOfDay(),
            ])
            ->selectRaw('DATE(scan_time) as scan_date, count(*) as total')
            ->groupBy('scan_date')
            ->orderBy('scan_date')
            ->pluck('total', 'scan_date')
            ->toArray();
    }
}
