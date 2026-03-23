<?php

namespace App\Services\HR;

use App\Models\HR\BiometricLog;
use App\Models\HR\DtrRecord;
use App\Models\HR\EmployeeSchedule;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DTRService
{
    /**
     * Generate or update DTR records for a user over a date range
     * by processing their resolved biometric logs.
     */
    public function generate(int $userId, string $dateFrom, string $dateTo): void
    {
        $from = Carbon::parse($dateFrom);
        $to   = Carbon::parse($dateTo);

        // Load all resolved biometric logs for this user in the range
        $allLogs = BiometricLog::where('user_id', $userId)
            ->where('is_resolved', true)
            ->where('is_duplicate', false)
            ->whereBetween('log_datetime', [
                $from->startOfDay()->format('Y-m-d H:i:s'),
                $to->endOfDay()->format('Y-m-d H:i:s'),
            ])
            ->orderBy('log_datetime')
            ->get()
            ->groupBy(fn ($log) => Carbon::parse($log->log_datetime)->toDateString());

        // Load holidays in range keyed by date
        $holidays = Holiday::where('is_active', true)
            ->whereBetween('holiday_date', [$dateFrom, $dateTo])
            ->get()
            ->keyBy(fn ($h) => Carbon::parse($h->holiday_date)->toDateString());

        // Load approved leave applications overlapping the range
        $leaves = LeaveApplication::where('user_id', $userId)
            ->where('status', 'approved')
            ->where('date_from', '<=', $dateTo)
            ->where('date_to', '>=', $dateFrom)
            ->get();

        // Iterate each calendar date in the range
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateStr = $date->toDateString();

            // Skip future dates
            if ($date->isFuture()) {
                continue;
            }

            // Check if locked
            $existing = DtrRecord::where('user_id', $userId)->where('work_date', $dateStr)->first();
            if ($existing && $existing->is_locked) {
                continue;
            }

            // Fetch the active schedule for this date
            $schedule = EmployeeSchedule::where('user_id', $userId)
                ->activeOnDate($dateStr)
                ->orderByDesc('effective_date')
                ->first();

            if (! $schedule) {
                $schedule = EmployeeSchedule::where('user_id', $userId)
                    ->where('is_default', true)
                    ->first();
            }

            // Determine day type
            [$dayType, $isWorkDay] = $this->getDayType($dateStr, $date, $schedule, $holidays);

            // Skip rest days entirely — weekend/off-day punches are not counted
            $logsForDay = $allLogs->get($dateStr, collect());
            if ($dayType === 'rest_day') {
                continue;
            }

            // Find approved leave covering this date
            $leave = $leaves->first(
                fn ($l) => $dateStr >= Carbon::parse($l->date_from)->toDateString()
                        && $dateStr <= Carbon::parse($l->date_to)->toDateString()
            );

            // Parse punches
            [$timeInAm, $timeOutAm, $timeInPm, $timeOutPm] = $this->parsePunches(
                $logsForDay,
                $dateStr,
                $schedule
            );

            // Compute metrics
            $hoursWorked      = $this->computeHoursWorked($timeInAm, $timeOutAm, $timeInPm, $timeOutPm);
            $lateMinutes      = $this->computeLateMinutes($timeInAm, $dateStr, $schedule);
            $undertimeMinutes = $this->computeUndertimeMinutes($timeOutPm, $dateStr, $schedule);
            $overtimeMinutes  = $this->computeOvertimeMinutes($timeOutPm, $dateStr, $schedule);

            // Determine attendance status
            $attendanceStatus = $this->getAttendanceStatus(
                $logsForDay,
                $leave,
                $dayType,
                $hoursWorked,
                $lateMinutes,
                $schedule
            );

            DtrRecord::updateOrCreate(
                ['user_id' => $userId, 'work_date' => $dateStr],
                [
                    'schedule_id'        => $schedule?->id,
                    'time_in_am'         => $timeInAm,
                    'time_out_am'        => $timeOutAm,
                    'time_in_pm'         => $timeInPm,
                    'time_out_pm'        => $timeOutPm,
                    'hours_worked'       => round($hoursWorked, 2),
                    'late_minutes'       => round($lateMinutes, 2),
                    'undertime_minutes'  => round($undertimeMinutes, 2),
                    'overtime_minutes'   => round($overtimeMinutes, 2),
                    'day_type'           => $dayType,
                    'attendance_status'  => $attendanceStatus,
                    'leave_application_id' => $leave?->id,
                    'processed_by'       => Auth::id(),
                    'processed_at'       => now(),
                ]
            );
        }
    }

    /**
     * Recompute derived time metrics on an existing DTR record (after manual edit).
     */
    public function recompute(DtrRecord $record): void
    {
        $date     = $record->work_date->toDateString();
        $schedule = $record->schedule;

        // Biometric value takes precedence; penned entry fills any null slot.
        $timeInAm  = $record->time_in_am  ?? $record->penned_time_in_am;
        $timeOutAm = $record->time_out_am ?? $record->penned_time_out_am;
        $timeInPm  = $record->time_in_pm  ?? $record->penned_time_in_pm;
        $timeOutPm = $record->time_out_pm ?? $record->penned_time_out_pm;

        $hoursWorked      = $this->computeHoursWorked($timeInAm, $timeOutAm, $timeInPm, $timeOutPm);
        $lateMinutes      = $this->computeLateMinutes($timeInAm, $date, $schedule);
        $undertimeMinutes = $this->computeUndertimeMinutes($timeOutPm, $date, $schedule);
        $overtimeMinutes  = $this->computeOvertimeMinutes($timeOutPm, $date, $schedule);

        $record->update([
            'hours_worked'      => round($hoursWorked, 2),
            'late_minutes'      => round($lateMinutes, 2),
            'undertime_minutes' => round($undertimeMinutes, 2),
            'overtime_minutes'  => round($overtimeMinutes, 2),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function getDayType(
        string $dateStr,
        Carbon $date,
        ?EmployeeSchedule $schedule,
        \Illuminate\Support\Collection $holidays
    ): array {
        $holiday    = $holidays->get($dateStr);
        $isWorkDay  = $schedule ? $schedule->isWorkDay($dateStr) : ! $date->isWeekend();

        if ($holiday) {
            $dayType = match ($holiday->type) {
                'regular'              => $isWorkDay ? 'holiday_regular' : 'rest_day_holiday',
                'special_non_working'  => $isWorkDay ? 'holiday_special' : 'rest_day_holiday',
                default                => 'regular',
            };
            return [$dayType, $isWorkDay];
        }

        return [$isWorkDay ? 'regular' : 'rest_day', $isWorkDay];
    }

    private function parsePunches(
        \Illuminate\Support\Collection $logs,
        string $dateStr,
        ?EmployeeSchedule $schedule
    ): array {
        if ($logs->isEmpty()) {
            return [null, null, null, null];
        }

        $sorted = $logs->sortBy('log_datetime')->values();

        // Split all punches into AM (before 12:30) and PM (12:30 and after).
        // 12:30 is used as the divider because it falls in the middle of the
        // standard Philippine government noon break (12:00–13:00), ensuring
        // a lunch-departure punch at e.g. 12:02 stays in the AM bucket while
        // a post-lunch return at e.g. 12:45 lands in PM.
        $breakMinutes = $this->getBreakMinutes($dateStr, $schedule);

        $amLogs = $sorted->filter(fn ($l) => $this->logMinutes($l) < $breakMinutes)->values();
        $pmLogs = $sorted->filter(fn ($l) => $this->logMinutes($l) >= $breakMinutes)->values();

        $hasExplicit = $logs->contains(fn ($l) => $l->log_type !== 'auto');

        if ($hasExplicit) {
            // Device provides Granding io_status codes:
            //   Check-In(0)/Break-In(3)/OT-In(4) → log_type = time_in
            //   Check-Out(1)/Break-Out(2)/OT-Out(5) → log_type = time_out
            // Within each time-of-day bucket use the type to find in/out.
            $amIns  = $amLogs->where('log_type', 'time_in')->values();
            $amOuts = $amLogs->where('log_type', 'time_out')->values();

            // Heuristic A — device sends io=0 for break-outs:
            // A single near-noon "time_in" with no AM time_out logs is actually
            // a lunch departure. Reclassify as time_out_am.
            if ($amIns->count() === 1
                && $amOuts->isEmpty()
                && $this->logMinutes($amIns->first()) >= ($breakMinutes - 90)
            ) {
                $timeInAm  = null;
                $timeOutAm = $this->firstTime($amIns);

            // Heuristic B — re-tap / bounce after departure:
            // Employee tapped the device again (time_in) within 5 minutes after
            // their last AM departure tap (time_out). Treat as noise; keep the
            // departure time and discard the stray time_in.
            } elseif ($amIns->count() === 1
                && $amOuts->isNotEmpty()
                && ($this->logMinutes($amIns->first()) - $this->logMinutes($amOuts->last())) <= 5
                && $this->logMinutes($amIns->first()) >= $this->logMinutes($amOuts->last())
            ) {
                $timeInAm  = null;
                $timeOutAm = $this->firstTime($amOuts);

            } else {
                $timeInAm  = $this->firstTime($amIns);
                $timeOutAm = $this->firstTime($amOuts);
            }

            $timeInPm  = $this->firstTime($pmLogs->where('log_type', 'time_in'));
            $timeOutPm = $this->lastTime($pmLogs->where('log_type', 'time_out'));
        } else {
            // 'auto' logs: use position within each bucket.
            $timeInAm  = $this->firstTime($amLogs);
            $timeOutAm = $amLogs->count() > 1 ? $this->lastTime($amLogs) : null;

            if ($pmLogs->count() >= 2) {
                // At least two PM punches: first = return from lunch, last = end of day
                $timeInPm  = $this->firstTime($pmLogs);
                $timeOutPm = $this->lastTime($pmLogs);
            } elseif ($pmLogs->count() === 1 && $amLogs->isNotEmpty()) {
                // Single PM punch while AM has activity → end-of-day departure
                $timeInPm  = null;
                $timeOutPm = $this->firstTime($pmLogs);
            } else {
                // Single PM punch with no AM activity → PM-only half-day arrival
                $timeInPm  = $this->firstTime($pmLogs);
                $timeOutPm = null;
            }
        }

        // Sanity-check ordering.  If out ≤ in it indicates a bounce / double-punch
        // artifact — the device was tapped twice in quick succession in the wrong
        // order.  Clear the invalid out rather than storing an impossible sequence.
        if ($timeInAm && $timeOutAm && $timeOutAm <= $timeInAm) {
            $timeOutAm = null;
        }
        if ($timeInPm && $timeOutPm && $timeOutPm <= $timeInPm) {
            $timeOutPm = null;
        }

        return [$timeInAm, $timeOutAm, $timeInPm, $timeOutPm];
    }

    /**
     * Split point between AM and PM sessions.
     * Uses 12:30 (750 min) — the midpoint of the standard noon break — so that
     * lunch-departure punches (~12:00–12:10) stay in AM and post-lunch returns
     * (~12:45–13:05) land in PM.  For non-standard shifts whose work midpoint
     * falls outside the 11:00–13:30 window the schedule midpoint is used instead.
     */
    private function getBreakMinutes(string $dateStr, ?EmployeeSchedule $schedule): int
    {
        if ($schedule) {
            $timeIn  = $schedule->getTimeIn($dateStr);
            $timeOut = $schedule->getTimeOut($dateStr);
            if ($timeIn && $timeOut) {
                $mid = intdiv($this->timeStrToMinutes($timeIn) + $this->timeStrToMinutes($timeOut), 2);
                // Only override the standard 12:30 split for genuinely non-noon shifts
                if ($mid < 660 || $mid > 810) { // outside 11:00–13:30
                    return $mid;
                }
            }
        }

        return 750; // 12:30 — midpoint of standard PH noon break
    }

    private function logMinutes(\App\Models\HR\BiometricLog $log): int
    {
        $dt = Carbon::parse($log->log_datetime);
        return $dt->hour * 60 + $dt->minute;
    }

    private function timeStrToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }

    private function firstTime(\Illuminate\Support\Collection $logs): ?string
    {
        $log = $logs->first();
        return $log ? Carbon::parse($log->log_datetime)->format('H:i:s') : null;
    }

    private function lastTime(\Illuminate\Support\Collection $logs): ?string
    {
        $log = $logs->last();
        return $log ? Carbon::parse($log->log_datetime)->format('H:i:s') : null;
    }

    private function computeHoursWorked(
        ?string $timeInAm,
        ?string $timeOutAm,
        ?string $timeInPm,
        ?string $timeOutPm
    ): float {
        $minutes = 0;

        if ($timeInAm && $timeOutAm) {
            $minutes += Carbon::parse($timeInAm)->diffInMinutes(Carbon::parse($timeOutAm));
        }

        if ($timeInPm && $timeOutPm) {
            $minutes += Carbon::parse($timeInPm)->diffInMinutes(Carbon::parse($timeOutPm));
        }

        // If only 2 punches (no explicit break), subtract 1-hour default lunch
        if (($timeInAm && $timeOutPm) && ! $timeOutAm && ! $timeInPm) {
            $total = Carbon::parse($timeInAm)->diffInMinutes(Carbon::parse($timeOutPm));
            $minutes = max(0, $total - 60);
        }

        return $minutes / 60;
    }

    private function computeLateMinutes(?string $timeInAm, string $dateStr, ?EmployeeSchedule $schedule): float
    {
        if (! $timeInAm || ! $schedule) {
            return 0;
        }

        $scheduledTimeIn = $schedule->getTimeIn($dateStr);
        if (! $scheduledTimeIn) {
            return 0;
        }

        $scheduledIn   = Carbon::parse($dateStr . ' ' . $scheduledTimeIn);
        $graceDeadline = $scheduledIn->copy()->addMinutes($schedule->grace_period_minutes ?? 15);
        $actualIn      = Carbon::parse($dateStr . ' ' . $timeInAm);

        if ($actualIn->lte($graceDeadline)) {
            return 0;
        }

        return max(0, $actualIn->diffInMinutes($scheduledIn, false));
    }

    private function computeUndertimeMinutes(?string $timeOutPm, string $dateStr, ?EmployeeSchedule $schedule): float
    {
        if (! $timeOutPm || ! $schedule) {
            return 0;
        }

        $scheduledTimeOut = $schedule->getTimeOut($dateStr);
        if (! $scheduledTimeOut) {
            return 0;
        }

        $scheduledOut = Carbon::parse($dateStr . ' ' . $scheduledTimeOut);
        $actualOut    = Carbon::parse($dateStr . ' ' . $timeOutPm);

        return max(0, $scheduledOut->diffInMinutes($actualOut, false));
    }

    private function computeOvertimeMinutes(?string $timeOutPm, string $dateStr, ?EmployeeSchedule $schedule): float
    {
        if (! $timeOutPm || ! $schedule) {
            return 0;
        }

        $scheduledTimeOut = $schedule->getTimeOut($dateStr);
        if (! $scheduledTimeOut) {
            return 0;
        }

        $scheduledOut = Carbon::parse($dateStr . ' ' . $scheduledTimeOut);
        $actualOut    = Carbon::parse($dateStr . ' ' . $timeOutPm);

        if ($actualOut->lte($scheduledOut)) {
            return 0;
        }

        return $actualOut->diffInMinutes($scheduledOut);
    }

    private function getAttendanceStatus(
        \Illuminate\Support\Collection $logs,
        mixed $leave,
        string $dayType,
        float $hoursWorked,
        float $lateMinutes,
        ?EmployeeSchedule $schedule
    ): string {
        if ($leave) {
            return 'on_leave';
        }

        if (in_array($dayType, ['holiday_regular', 'holiday_special', 'rest_day_holiday']) && $logs->isEmpty()) {
            return 'holiday';
        }

        if ($logs->isEmpty()) {
            return 'absent';
        }

        $halfDayThreshold = $schedule ? (($schedule->late_threshold_minutes ?? 240) / 60) : 4;

        if ($hoursWorked < $halfDayThreshold || $lateMinutes >= ($schedule->late_threshold_minutes ?? 240)) {
            return 'half_day';
        }

        return 'present';
    }
}
