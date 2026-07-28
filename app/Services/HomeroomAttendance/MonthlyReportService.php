<?php

namespace App\Services\HomeroomAttendance;

use App\Models\HomeroomAttendance\AttendanceDate;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\HomeroomAttendance\AttendanceSetting;
use App\Models\HomeroomAttendance\MonthlyReport;
use App\Models\HomeroomAttendance\MonthlyReportLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the monthly Record on Attendance and Punctuality for a section from
 * the Daily/Activity attendance logged that month, following CIM 7.0's
 * deduction rules.
 *
 * "Days Present" is a weighted figure — school days minus tardy/cutting/
 * unexcused-absence deductions — NOT reduced for excused absences. That
 * matches the one concrete example in the source form (an excused,
 * activity-related absence left "days present" unchanged) and the manual's
 * note that no deduction is imposed for infractions tied to an official
 * Special/Travel Order. If PSHS-CRC also wants *other* excused absences
 * (e.g. medical) to reduce days present while staying off the disciplinary
 * "unexcused" count, that would need a third excused sub-category — flagged
 * as an open question, not assumed here.
 */
class MonthlyReportService
{
    public function __construct(private RosterService $roster)
    {
    }

    public function generate(int $sectionId, int $schoolYearId, int $year, int $month): MonthlyReport
    {
        return DB::transaction(function () use ($sectionId, $schoolYearId, $year, $month) {
            $report = MonthlyReport::firstOrCreate(
                ['section_id' => $sectionId, 'year' => $year, 'month' => $month],
                ['school_year_id' => $schoolYearId, 'status' => 'draft'],
            );

            abort_if(
                $report->status !== 'draft',
                422,
                'This report has already been submitted and can no longer be regenerated.',
            );

            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $dateIds = AttendanceDate::where('section_id', $sectionId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->pluck('id');

            $schoolDaysCount = $dateIds->count();
            $report->school_days_count = $schoolDaysCount;
            $report->save();

            $recordsByStudent = AttendanceRecord::whereIn('homeroom_attendance_date_id', $dateIds)
                ->get()
                ->groupBy('student_id');

            $deductions = AttendanceSetting::deductionPoints();
            $students = $this->roster->studentsForSection($sectionId, $schoolYearId);

            foreach ($students as $student) {
                $studentRecords = $recordsByStudent->get($student->id, collect());

                $excusedAbsences = $studentRecords->where('status', 'absent')->where('excused_status', 'excused')->count();
                $unexcusedAbsences = $studentRecords->where('status', 'absent')->where('excused_status', '!=', 'excused')->count();
                $cutting = $studentRecords->where('status', 'cutting')->count();
                $tardy = $studentRecords->where('status', 'tardy')->count();
                $incUniform = $studentRecords->where('incomplete_uniform', true)->count();

                $deductionPoints = round(
                    ($tardy * $deductions['tardy'])
                    + ($cutting * $deductions['cutting'])
                    + ($unexcusedAbsences * $deductions['absence']),
                    2,
                );

                $daysPresent = max(0, round($schoolDaysCount - $deductionPoints, 2));

                $isPerfect = $excusedAbsences === 0 && $unexcusedAbsences === 0 && $cutting === 0 && $tardy === 0 && $incUniform === 0;

                MonthlyReportLine::updateOrCreate(
                    ['homeroom_monthly_report_id' => $report->id, 'student_id' => $student->id],
                    [
                        'days_present'          => $daysPresent,
                        'excused_absences'      => $excusedAbsences,
                        'unexcused_absences'    => $unexcusedAbsences,
                        'cutting_count'         => $cutting,
                        'tardy_count'           => $tardy,
                        'inc_uniform_count'     => $incUniform,
                        'deduction_points'      => $deductionPoints,
                        'remarks'               => $isPerfect ? 'Perfect Attendance' : null,
                        'is_perfect_attendance' => $isPerfect,
                    ],
                );
            }

            // Drop lines for students no longer on this section's roster.
            $report->lines()->whereNotIn('student_id', $students->pluck('id'))->delete();

            return $report->load('lines.student');
        });
    }

    public function submit(MonthlyReport $report, int $userId): MonthlyReport
    {
        abort_unless($report->status === 'draft', 422, 'Only a draft report can be submitted.');

        $report->update([
            'status'       => 'submitted',
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);

        return $report;
    }

    public function approve(MonthlyReport $report, int $userId, ?string $remarks): MonthlyReport
    {
        abort_unless($report->status === 'submitted', 422, 'Only a submitted report can be approved.');

        $report->update([
            'status'               => 'approved',
            'approved_by'          => $userId,
            'approved_at'          => now(),
            'coordinator_remarks'  => $remarks,
        ]);

        return $report;
    }

    public function returnToDraft(MonthlyReport $report, string $remarks): MonthlyReport
    {
        abort_unless($report->status === 'submitted', 422, 'Only a submitted report can be returned.');

        $report->update([
            'status'              => 'draft',
            'submitted_by'        => null,
            'submitted_at'        => null,
            'coordinator_remarks' => $remarks,
        ]);

        return $report;
    }
}
