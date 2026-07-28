<?php

namespace App\Services\HomeroomAttendance;

use App\Models\HomeroomAttendance\FlagAttendanceDate;
use App\Models\HomeroomAttendance\FlagAttendanceRecord;
use Illuminate\Support\Facades\DB;

/**
 * Logs whole/part-day attendance for the two recurring school ceremonies
 * (Flag Ceremony, Flag Retreat) — deliberately separate from
 * DailyAttendanceService: these events only cover part of the day, so they
 * must never feed the whole-day homeroom_attendance_records table (that
 * would corrupt the days-present formula and derived cutting detection —
 * see project_homeroom_attendance_module notes).
 */
class FlagAttendanceService
{
    public function findDate(int $sectionId, string $eventType, string $date): ?FlagAttendanceDate
    {
        return FlagAttendanceDate::where('section_id', $sectionId)
            ->where('event_type', $eventType)
            ->where('date', $date)
            ->with('records')
            ->first();
    }

    /**
     * @param  array<int, array{student_id:int, status:string, remarks?:?string}>  $rows
     */
    public function saveDay(
        int $sectionId,
        int $schoolYearId,
        string $eventType,
        string $date,
        int $userId,
        array $rows,
    ): FlagAttendanceDate {
        return DB::transaction(function () use ($sectionId, $schoolYearId, $eventType, $date, $userId, $rows) {
            $attendanceDate = FlagAttendanceDate::firstOrCreate(
                ['section_id' => $sectionId, 'event_type' => $eventType, 'date' => $date],
                ['school_year_id' => $schoolYearId, 'taken_by' => $userId],
            );
            $attendanceDate->taken_by = $userId;
            $attendanceDate->save();

            foreach ($rows as $row) {
                FlagAttendanceRecord::updateOrCreate(
                    [
                        'homeroom_flag_attendance_date_id' => $attendanceDate->id,
                        'student_id'                       => $row['student_id'],
                    ],
                    [
                        'status'  => $row['status'],
                        'remarks' => $row['remarks'] ?? null,
                    ],
                );
            }

            return $attendanceDate->load('records');
        });
    }
}
