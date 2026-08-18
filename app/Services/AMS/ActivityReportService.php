<?php

namespace App\Services\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityReportService
{
    public function __construct(
        private ActivityEvaluationEligibilityService $evaluationEligibility,
    ) {}

    public function buildReport(Activity $activity): array
    {
        $evaluations = $this->evaluationEligibility->evaluatedMap($activity);
        $days        = $activity->attendanceDayList();

        $dailyRows = $activity->isMultiDay()
            ? ActivityAttendanceDay::where('activity_id', $activity->id)->get()
                ->groupBy(fn ($row) => $row->participant_type . ':' . $row->participant_id)
            : collect();

        $rows = collect();

        $employeeParticipants = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_type', 'employee')->get();
        $employeesMap = User::whereIn('id', $employeeParticipants->pluck('participant_id'))
            ->with('division')->get(['id', 'name', 'division_id'])->keyBy('id');

        foreach ($employeeParticipants as $p) {
            $employee = $employeesMap[$p->participant_id] ?? null;
            $rows->push($this->buildRow(
                name: $employee?->name ?? "Employee #{$p->participant_id}",
                type: 'Employee',
                sectionLabel: $employee?->division?->division_name,
                attended: $p->attended,
                hours: (float) $p->hours_attended,
                evaluated: isset($evaluations['employee:' . $p->participant_id]),
                certificateIssued: (bool) $p->certificate_path,
                days: $days,
                dailyForParticipant: $dailyRows->get('employee:' . $p->participant_id, collect()),
            ));
        }

        $studentRows = ActivityStudentAttendance::where('activity_id', $activity->id)->get();
        $studentIds  = $studentRows->pluck('participant_id');
        $studentsMap = Student::whereIn('id', $studentIds)->get(['id', 'firstname', 'lastname', 'middlename'])->keyBy('id');

        $studentSectionIds = DB::table('section_students')->whereIn('studentid', $studentIds)->pluck('sectionid', 'studentid');
        $sectionsMap = Section::whereIn('id', $studentSectionIds->unique()->values())
            ->get(['id', 'sectionname', 'levelid'])->keyBy('id');

        foreach ($studentRows as $r) {
            $student = $studentsMap[$r->participant_id] ?? null;
            $section = $sectionsMap[$studentSectionIds[$r->participant_id] ?? null] ?? null;

            $rows->push($this->buildRow(
                name: $student?->full_name ?? "Student #{$r->participant_id}",
                type: 'Student',
                sectionLabel: $section ? "Grade {$section->levelid} — {$section->sectionname}" : null,
                attended: $r->attended,
                hours: (float) $r->hours_attended,
                evaluated: isset($evaluations['student:' . $r->participant_id]),
                certificateIssued: (bool) $r->certificate_path,
                days: $days,
                dailyForParticipant: $dailyRows->get('student:' . $r->participant_id, collect()),
            ));
        }

        $totalInvited      = $rows->count();
        $totalPresent       = $rows->where('attended', true)->count();
        $totalEvaluated     = $rows->where('evaluated', true)->count();
        $totalCertificates  = $rows->where('certificate_issued', true)->count();

        return [
            'days' => $days,
            'kpis' => [
                'invited'             => $totalInvited,
                'present'             => $totalPresent,
                'attendance_rate'     => $totalInvited > 0 ? round($totalPresent / $totalInvited * 100, 1) : 0.0,
                'evaluated'           => $totalEvaluated,
                'evaluation_rate'     => $totalInvited > 0 ? round($totalEvaluated / $totalInvited * 100, 1) : 0.0,
                'certificates_issued' => $totalCertificates,
            ],
            'rows' => $rows->values()->all(),
        ];
    }

    private function buildRow(
        string $name,
        string $type,
        ?string $sectionLabel,
        string $attended,
        float $hours,
        bool $evaluated,
        bool $certificateIssued,
        array $days,
        Collection $dailyForParticipant,
    ): array {
        $dailyByDate = $dailyForParticipant->keyBy('date');

        return [
            'name'               => $name,
            'type'               => $type,
            'section'            => $sectionLabel,
            'attended'           => $attended === 'yes',
            'hours_attended'     => $hours,
            'evaluated'          => $evaluated,
            'certificate_issued' => $certificateIssued,
            'daily'              => collect($days)->map(fn ($date) => [
                'date'     => $date,
                'attended' => ($dailyByDate->get($date)?->attended ?? 'no') === 'yes',
            ])->values()->all(),
        ];
    }
}
