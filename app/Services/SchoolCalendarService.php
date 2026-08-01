<?php

namespace App\Services;

use App\Models\SchoolCalendarEvent;
use Carbon\CarbonImmutable;
use RuntimeException;

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
        if (CarbonImmutable::parse($date)->isWeekend()) {
            return false;
        }

        return ! SchoolCalendarEvent::where('date', $date)
            ->where(function ($query) use ($gradeLevel) {
                $query->whereNull('grade_level');
                if ($gradeLevel !== null) {
                    $query->orWhere('grade_level', $gradeLevel);
                }
            })
            ->exists();
    }

    /**
     * Find the next weekday on which every requested grade has classes.
     * Campus-wide and grade-specific academic-calendar events are respected.
     *
     * @param array<int,int> $gradeLevels
     */
    public function nextSchoolDayAfter(string $date, array $gradeLevels = [7, 8, 9, 10, 11, 12], ?string $notAfter = null): string
    {
        $candidate = CarbonImmutable::parse($date)->addDay();
        $limit = $notAfter
            ? CarbonImmutable::parse($notAfter)
            : $candidate->addDays(45);

        while ($candidate->lessThanOrEqualTo($limit)) {
            if (! $candidate->isWeekend()) {
                $events = SchoolCalendarEvent::whereDate('date', $candidate->toDateString())
                    ->get(['grade_level']);

                $campusClosed = $events->contains(fn ($event) => $event->grade_level === null);
                $closedGrades = $events->pluck('grade_level')->filter()->map(fn ($grade) => (int) $grade)->all();

                if (! $campusClosed && array_intersect($gradeLevels, $closedGrades) === []) {
                    return $candidate->toDateString();
                }
            }

            $candidate = $candidate->addDay();
        }

        throw new RuntimeException('No common school day was found within the academic term.');
    }
}
