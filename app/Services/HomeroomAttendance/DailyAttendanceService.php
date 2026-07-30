<?php

namespace App\Services\HomeroomAttendance;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\HomeroomAttendance\AttendanceDate;
use App\Models\HomeroomAttendance\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyAttendanceService
{
    public function __construct(private SubjectAttendanceSyncService $subjectSync)
    {
    }

    public function findDate(int $sectionId, string $date): ?AttendanceDate
    {
        return AttendanceDate::where('section_id', $sectionId)
            ->where('date', $date)
            ->with('records')
            ->first();
    }

    /**
     * Create/update the day's attendance for a section, then push each
     * student's whole-day status into their subject-level Class Records for
     * the same date (SubjectAttendanceSyncService) — so subject teachers see
     * the day pre-filled and only need to correct exceptions.
     *
     * Excused/unexcused status is intentionally not set here — that's
     * decided later by the Registrar when a Class Admission Slip is issued
     * (AdmissionSlipService).
     *
     * @param  array<int, array{student_id:int, status:string, incomplete_uniform?:bool, remarks?:?string}>  $rows
     */
    public function saveDay(int $sectionId, int $schoolYearId, string $date, int $userId, array $rows): AttendanceDate
    {
        return DB::transaction(function () use ($sectionId, $schoolYearId, $date, $userId, $rows) {
            $attendanceDate = AttendanceDate::firstOrCreate(
                ['section_id' => $sectionId, 'date' => $date],
                ['school_year_id' => $schoolYearId, 'taken_by' => $userId],
            );
            $attendanceDate->taken_by = $userId;
            $attendanceDate->save();

            foreach ($rows as $row) {
                AttendanceRecord::updateOrCreate(
                    [
                        'homeroom_attendance_date_id' => $attendanceDate->id,
                        'student_id'                  => $row['student_id'],
                    ],
                    [
                        'status'             => $row['status'],
                        'incomplete_uniform' => $row['incomplete_uniform'] ?? false,
                        'remarks'            => $row['remarks'] ?? null,
                    ],
                );

                $this->subjectSync->syncForStudent($row['student_id'], $date, $row['status']);
            }

            return $attendanceDate->load('records');
        });
    }

    /**
     * "Who submitted attendance today" visibility panel for the Homeroom
     * Adviser: every subject actually scheduled to meet this section on
     * this day of the week, and whether that subject's Class Record
     * Attendance grid has any saved records for this exact date. Purely
     * informational — the adviser cannot act on it, it just tells them
     * whether the Cut Class/IU signals they're seeing are complete yet or
     * some subject teachers simply haven't logged today at all.
     *
     * A subject counts as "submitted" once at least one attendance row
     * exists for that date/subject scope — the auto-created Present rows
     * from storeDates()/SubjectAttendanceSyncService count too, same as a
     * teacher's own manual save, since either way the date now reflects
     * real data rather than nothing.
     *
     * @return array<int, array{subject_id:?int, subject_name:string, teacher_id:?int, teacher_name:string, submitted:bool}>
     */
    public function subjectSubmissionStatus(int $sectionId, string $date): array
    {
        $dayOfWeek = Carbon::parse($date)->format('l');

        $scheduledSubjects = ClassSchedule::classes()
            ->where('section_id', $sectionId)
            ->onDay($dayOfWeek)
            ->with(['subject:id,name', 'faculty:id,name'])
            ->get()
            ->unique(fn ($s) => ($s->subject_id ?? 0) . '_' . ($s->user_id ?? 0));

        if ($scheduledSubjects->isEmpty()) {
            return [];
        }

        // Every subject_id (real, or synthesized as class_records.subject_id
        // on a non-shared record) that has ANY saved attendance row for this
        // section on this date, regardless of which teacher/co-teacher saved
        // it — cheaper than re-deriving per-teacher ownership here, and the
        // panel only needs a per-subject submitted/not-submitted signal.
        $submittedSubjectIds = DB::table('class_record_attendance_records as car')
            ->join('class_record_attendance_dates as cad', 'cad.id', '=', 'car.class_record_attendance_date_id')
            ->join('class_record_students as crs', 'crs.id', '=', 'car.class_record_student_id')
            ->join('class_record_quarters as crq', 'crq.id', '=', 'crs.class_record_quarter_id')
            ->join('class_records as cr', 'cr.id', '=', 'crq.class_record_id')
            ->where('cr.section_id', $sectionId)
            ->where('cad.date', $date)
            ->select(DB::raw('DISTINCT COALESCE(cad.subject_id, cr.subject_id) as subject_id'))
            ->pluck('subject_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return $scheduledSubjects->map(function (ClassSchedule $schedule) use ($submittedSubjectIds) {
            return [
                'subject_id'   => $schedule->subject_id,
                'subject_name' => $schedule->subject?->name ?? 'Unknown Subject',
                'teacher_id'   => $schedule->user_id,
                'teacher_name' => $schedule->faculty?->name ?? 'Unassigned',
                'submitted'    => $schedule->subject_id !== null && in_array((int) $schedule->subject_id, $submittedSubjectIds, true),
            ];
        })->values()->all();
    }
}
