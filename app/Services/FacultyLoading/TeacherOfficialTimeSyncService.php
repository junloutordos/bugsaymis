<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\TeacherOfficialTime;
use App\Models\HR\EmployeeSchedule;

/**
 * Syncs a faculty member's HR-approved work schedule (employee_schedules)
 * into teacher_official_times — the table the class-schedule print sheet,
 * "My Faculty Schedule", and the scheduler's H3 hard constraint all read.
 *
 * Runs at the moment HR approves an EmployeeSchedule submission, so
 * official time + lunch on the printed/individual faculty schedule always
 * mirror what the faculty member actually submitted and HR approved —
 * never a guess, never a stale default.
 */
class TeacherOfficialTimeSyncService
{
    /** employee_schedules daily_schedules keys → teacher_official_times day_of_week values. */
    private const DAY_MAP = [
        'Mon' => 'Monday',
        'Tue' => 'Tuesday',
        'Wed' => 'Wednesday',
        'Thu' => 'Thursday',
        'Fri' => 'Friday',
    ];

    /**
     * Upsert one teacher_official_times row per work day found in the
     * schedule's daily_schedules. Only Monday–Friday are synced — the
     * scheduler/print sheet have no concept of Saturday/Sunday classes.
     */
    public function syncFromEmployeeSchedule(EmployeeSchedule $schedule): void
    {
        $dailySchedules = $schedule->daily_schedules ?? [];

        foreach (self::DAY_MAP as $shortDay => $fullDay) {
            $day = $dailySchedules[$shortDay] ?? null;

            if (! $day || empty($day['time_in']) || empty($day['time_out'])) {
                continue;
            }

            TeacherOfficialTime::updateOrCreate(
                ['user_id' => $schedule->user_id, 'day_of_week' => $fullDay],
                [
                    'start_time' => $day['time_in'],
                    'end_time' => $day['time_out'],
                    'lunch_start' => $day['lunch_start'] ?? null,
                    'lunch_end' => $day['lunch_end'] ?? null,
                ],
            );
        }
    }
}
