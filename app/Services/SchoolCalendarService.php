<?php

namespace App\Services;

use App\Models\SchoolCalendarEvent;

/**
 * Whether a given date is an actual school day — no holiday/suspension
 * flagged campus-wide or for the given grade level. Used to stop the
 * schedule-driven subject-attendance sync from creating attendance dates
 * on days classes didn't happen, and to keep the monthly Record on
 * Attendance and Punctuality's school-days count honest.
 */
class SchoolCalendarService
{
    public function isSchoolDay(string $date, ?int $gradeLevel = null): bool
    {
        return ! SchoolCalendarEvent::where('date', $date)
            ->where(function ($query) use ($gradeLevel) {
                $query->whereNull('grade_level');
                if ($gradeLevel !== null) {
                    $query->orWhere('grade_level', $gradeLevel);
                }
            })
            ->exists();
    }
}
