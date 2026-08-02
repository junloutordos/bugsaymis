<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\HomeroomAttendance\MonthlyReportLine;
use App\Models\Student;
use App\Models\User;

class GetHomeroomAttendanceSummaryTool implements DynaTool
{
    public function name(): string { return 'get_homeroom_attendance_summary'; }

    public function description(): string
    {
        return 'Returns homeroom attendance data. Without student_identifier: campus-wide averages (cutting incidents, '
             . 'perfect-attendance count, excused-vs-unexcused ratio). With student_identifier: that specific student\'s monthly record. '
             . 'Requires homeroom attendance admin access.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_identifier' => [
                    'type' => 'string',
                    'description' => 'Optional — a student name or ID to look up that specific student\'s attendance record instead of the campus-wide summary.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('homeroom-attendance.admin')) {
            throw new \RuntimeException('This account does not have homeroom attendance admin access.');
        }

        if (! empty($input['student_identifier'])) {
            return $this->individualRecord($input['student_identifier']);
        }

        $lines = MonthlyReportLine::all();

        return [
            'perfect_attendance_count' => $lines->where('is_perfect_attendance', true)->count(),
            'average_cutting_count' => round($lines->avg('cutting_count'), 2),
            'total_excused_absences' => $lines->sum('excused_absences'),
            'total_unexcused_absences' => $lines->sum('unexcused_absences'),
        ];
    }

    private function individualRecord(string $identifier): array
    {
        $student = Student::where('lastname', 'like', "%{$identifier}%")
            ->orWhere('firstname', 'like', "%{$identifier}%")
            ->orWhere('pisaysystemID', $identifier)
            ->first();

        if (! $student) {
            return ['records' => [], 'note' => "No student found matching \"{$identifier}\"."];
        }

        $lines = MonthlyReportLine::where('student_id', $student->id)->get([
            'days_present', 'excused_absences', 'unexcused_absences', 'cutting_count', 'tardy_count', 'is_perfect_attendance',
        ]);

        return [
            'student' => $student->full_name,
            'records' => $lines->toArray(),
        ];
    }
}
