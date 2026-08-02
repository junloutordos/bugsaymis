<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;

class GetTeacherAttendanceStatsTool implements DynaTool
{
    public function name(): string { return 'get_teacher_attendance_stats'; }

    public function description(): string
    {
        return 'Returns teacher NFC tap-attendance status distribution (on_time/late/no_match counts). '
             . 'Use for questions about teacher punctuality or attendance tracking data quality.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function execute(User $user, array $input): array
    {
        $query = TeacherTapLog::query();

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('teacher', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
