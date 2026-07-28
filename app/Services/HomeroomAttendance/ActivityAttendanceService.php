<?php

namespace App\Services\HomeroomAttendance;

use App\Models\HomeroomAttendance\ActivityLog;
use App\Models\HomeroomAttendance\ActivityRecord;
use App\Models\HomeroomAttendance\AttendanceDate;
use App\Models\HomeroomAttendance\AttendanceRecord;
use Illuminate\Support\Facades\DB;

class ActivityAttendanceService
{
    /**
     * @param  array<int, array{student_id:int, am_status:string, pm_status:string, remarks?:?string}>  $rows
     */
    public function record(
        int $sectionId,
        int $schoolYearId,
        string $title,
        string $activityDate,
        bool $isOffCampus,
        int $userId,
        array $rows,
    ): ActivityLog {
        return DB::transaction(function () use ($sectionId, $schoolYearId, $title, $activityDate, $isOffCampus, $userId, $rows) {
            $log = ActivityLog::create([
                'section_id'    => $sectionId,
                'title'         => $title,
                'activity_date' => $activityDate,
                'is_off_campus' => $isOffCampus,
                'created_by'    => $userId,
            ]);

            foreach ($rows as $row) {
                ActivityRecord::create([
                    'homeroom_activity_log_id' => $log->id,
                    'student_id'                => $row['student_id'],
                    'am_status'                 => $row['am_status'],
                    'pm_status'                 => $row['pm_status'],
                    'remarks'                   => $row['remarks'] ?? null,
                ]);
            }

            // A whole-day activity is the operative record for that date when
            // no regular Daily Attendance was taken (e.g. an all-day/off-campus
            // event) — mirror each student's derived whole-day status into
            // homeroom_attendance_records so the monthly report sees it,
            // without overwriting a Daily entry that already exists for that day.
            if (! AttendanceDate::where('section_id', $sectionId)->where('date', $activityDate)->exists()) {
                $attendanceDate = AttendanceDate::create([
                    'section_id'     => $sectionId,
                    'school_year_id' => $schoolYearId,
                    'date'           => $activityDate,
                    'taken_by'       => $userId,
                ]);

                foreach ($log->records as $record) {
                    AttendanceRecord::create([
                        'homeroom_attendance_date_id' => $attendanceDate->id,
                        'student_id'                  => $record->student_id,
                        'status'                      => $record->whole_day_status,
                        'remarks'                     => 'Auto-derived from activity: '.$title,
                    ]);
                }
            }

            return $log->load('records.student');
        });
    }
}
