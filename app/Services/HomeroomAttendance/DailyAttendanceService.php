<?php

namespace App\Services\HomeroomAttendance;

use App\Models\HomeroomAttendance\AttendanceDate;
use App\Models\HomeroomAttendance\AttendanceRecord;
use Illuminate\Support\Facades\DB;

class DailyAttendanceService
{
    public function findDate(int $sectionId, string $date): ?AttendanceDate
    {
        return AttendanceDate::where('section_id', $sectionId)
            ->where('date', $date)
            ->with('records')
            ->first();
    }

    /**
     * Create/update the day's attendance for a section. Excused/unexcused
     * status is intentionally not set here — that's decided later by the
     * Registrar when a Class Admission Slip is issued (AdmissionSlipService).
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
            }

            return $attendanceDate->load('records');
        });
    }
}
