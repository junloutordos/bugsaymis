<?php

namespace App\Services\HR;

use App\Models\HR\BiometricLog;
use App\Models\HR\DtrRecord;
use App\Models\HR\EmployeeSchedule;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveApplication;
use App\Models\User;
use App\Models\WFHAttendance;
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

        // For overnight shifts, we need logs from the day AFTER $to as well
        // (the time-out punch of the last overnight shift lands on $to+1)
        $logQueryTo = $to->copy()->addDay()->endOfDay();

        $allLogs = BiometricLog::where('user_id', $userId)
            ->where('is_resolved', true)
            ->where('is_duplicate', false)
            ->whereBetween('log_datetime', [
                $from->startOfDay()->format('Y-m-d H:i:s'),
                $logQueryTo->format('Y-m-d H:i:s'),
            ])
            ->orderBy('log_datetime')
            ->get();
        // Do NOT group by date yet — we'll do shift-aware grouping per date below

        // Load holidays in range keyed by date
        $holidays = Holiday::where('is_active', true)
            ->whereBetween('holiday_date', [$dateFrom, $dateTo])
            ->get()
            ->keyBy(fn ($h) => Carbon::parse($h->holiday_date)->toDateString());

        // Load WFH attendances for this range keyed by date
        $wfhAttendances = WFHAttendance::where('user_id', $userId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get()
            ->keyBy(fn ($w) => Carbon::parse($w->date)->toDateString());

        // Load approved leave applications overlapping the range
        $leaves = LeaveApplication::where('user_id', $userId)
            ->where('status', 'approved')
            ->where('date_from', '<=', $dateTo)
            ->where('date_to', '>=', $dateFrom)
            ->get();

        // COS employees may generate one advance day ahead for cut-off processing
        $user      = User::find($userId);
        $isCos     = in_array($user?->emp_category ?? '', ['COS Teaching', 'COS Non Teaching']);
        $tomorrow  = now()->addDay()->startOfDay();

        // Iterate each calendar date in the range
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateStr   = $date->toDateString();
            $isAdvance = $date->isFuture() && $isCos && $date->lte($tomorrow);

            // Skip future dates; COS employees may generate one advance day (cut-off)
            if ($date->isFuture() && ! $isAdvance) {
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
            $logsForDay = $this->getLogsForShift($allLogs, $dateStr, $schedule);
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

            // WFH fallback: when there are no biometric logs for this date but
            // the employee has a WFH attendance record, map all four WFH slots
            // onto the DTR time columns so hours & late/undertime compute correctly.
            //   time_in   → time_in_am  (morning arrival)
            //   break_out → time_out_am (lunch departure)
            //   break_in  → time_in_pm  (return from lunch)
            //   time_out  → time_out_pm (end of day)
            $wfh = $wfhAttendances->get($dateStr);
            if ($wfh && $logsForDay->isEmpty()) {
                $timeInAm  = $wfh->time_in   ? Carbon::parse($wfh->time_in)->format('H:i:s')   : null;
                $timeOutAm = $wfh->break_out  ? Carbon::parse($wfh->break_out)->format('H:i:s') : null;
                $timeInPm  = $wfh->break_in   ? Carbon::parse($wfh->break_in)->format('H:i:s')  : null;
                $timeOutPm = $wfh->time_out   ? Carbon::parse($wfh->time_out)->format('H:i:s')  : null;
            }

            // Compute metrics
            $isOvernight      = $schedule && $schedule->isOvernightShift($dateStr);
            $hoursWorked      = $this->computeHoursWorked($timeInAm, $timeOutAm, $timeInPm, $timeOutPm, $isOvernight);
            $lateMinutes      = $this->computeLateMinutes($timeInAm, $timeInPm, $dateStr, $schedule);
            $undertimeMinutes = $this->computeUndertimeMinutes($timeOutAm, $timeOutPm, $dateStr, $schedule);
            $overtimeMinutes  = $this->computeOvertimeMinutes($timeOutPm, $dateStr, $schedule);

            // Determine attendance status.
            // WFH days (no biometric logs, WFH attendance present) get their own
            // status so they are never counted as absent.
            if ($wfh && $logsForDay->isEmpty() && ! $leave) {
                $attendanceStatus = 'wfh';
            } else {
                $attendanceStatus = $this->getAttendanceStatus(
                    $logsForDay,
                    $leave,
                    $dayType,
                    $hoursWorked,
                    $lateMinutes,
                    $schedule,
                    $timeInAm,
                    $timeOutPm
                );
            }

            DtrRecord::updateOrCreate(
                ['user_id' => $userId, 'work_date' => $dateStr],
                [
                    'schedule_id'          => $schedule?->id,
                    'time_in_am'           => $timeInAm,
                    'time_out_am'          => $timeOutAm,
                    'time_in_pm'           => $timeInPm,
                    'time_out_pm'          => $timeOutPm,
                    'hours_worked'         => round($hoursWorked, 2),
                    'late_minutes'         => round($lateMinutes, 2),
                    'undertime_minutes'    => round($undertimeMinutes, 2),
                    'overtime_minutes'     => round($overtimeMinutes, 2),
                    'day_type'             => $dayType,
                    'attendance_status'    => $attendanceStatus,
                    'leave_application_id' => $leave?->id,
                    'wfh_attendance_id'    => ($wfh && $logsForDay->isEmpty()) ? $wfh->id : null,
                    'is_advance'           => $isAdvance,
                    'processed_by'         => Auth::id(),
                    'processed_at'         => now(),
                ]
            );
        }
    }

    /**
     * Apply a gate pass time deduction to the employee's DTR record for the given date.
     * Called when actual_timeout + actual_timein are recorded on an approved gate pass.
     * Deducts the time spent outside from hours_worked and, for UT-type passes, adds
     * the consumed minutes to undertime_minutes.
     */
    public function applyGatepassToDate(
        int    $userId,
        string $date,
        string $actualTimeout,
        string $actualTimein,
        string $gatepassType,
    ): void {
        $record = DtrRecord::where('user_id', $userId)
            ->where('work_date', $date)
            ->where('is_locked', false)
            ->first();

        if (! $record) {
            return;
        }

        try {
            $out             = Carbon::parse($actualTimeout);
            $in              = Carbon::parse($actualTimein);
            $consumedMinutes = max(0, (int) $out->diffInMinutes($in));
        } catch (\Throwable) {
            return;
        }

        if ($consumedMinutes === 0) {
            return;
        }

        $hoursConsumed = round($consumedMinutes / 60, 2);
        $updates       = [
            'hours_worked' => max(0, round((float) $record->hours_worked - $hoursConsumed, 2)),
        ];

        // UT gate passes explicitly represent undertime — count toward undertime minutes.
        // OB and OT are authorised absences; we still reduce hours_worked but do not
        // double-count them as undertime (the schedule recompute already captures that).
        if (strtolower(trim($gatepassType)) === 'undertime') {
            $updates['undertime_minutes'] = round((float) $record->undertime_minutes + $consumedMinutes, 2);
        }

        $record->update($updates);
    }

    /**
     * When a leave application is finally approved, update any existing (unlocked)
     * DTR records within its date range to reflect on_leave status immediately —
     * without waiting for HR to re-run DTR generation.
     */
    public function applyLeaveToExistingDtrRecords(LeaveApplication $leave): void
    {
        $dateFrom = Carbon::parse($leave->date_from)->toDateString();
        $dateTo   = Carbon::parse($leave->date_to)->toDateString();

        DtrRecord::where('user_id', $leave->user_id)
            ->whereBetween('work_date', [$dateFrom, $dateTo])
            ->update([
                'attendance_status'    => 'on_leave',
                'leave_application_id' => $leave->id,
            ]);
    }

    /**
     * Recompute derived time metrics on an existing DTR record (after manual edit).
     */
    public function recompute(DtrRecord $record): void
    {
        $date     = $record->work_date->toDateString();
        $schedule = $record->schedule;

        // If no schedule is linked to this record, re-resolve the active schedule
        // for the employee on this date (covers records generated before a schedule
        // was assigned, or after a schedule change).
        if (! $schedule) {
            $schedule = EmployeeSchedule::where('user_id', $record->user_id)
                ->activeOnDate($date)
                ->orderByDesc('effective_date')
                ->first();

            if (! $schedule) {
                $schedule = EmployeeSchedule::where('user_id', $record->user_id)
                    ->where('is_default', true)
                    ->first();
            }

            if ($schedule) {
                $record->schedule_id = $schedule->id;
            }
        }

        // Biometric value takes precedence; penned entry fills any null slot.
        $timeInAm  = $record->time_in_am  ?? $record->penned_time_in_am;
        $timeOutAm = $record->time_out_am ?? $record->penned_time_out_am;
        $timeInPm  = $record->time_in_pm  ?? $record->penned_time_in_pm;
        $timeOutPm = $record->time_out_pm ?? $record->penned_time_out_pm;

        $isOvernight      = $schedule && $schedule->isOvernightShift($date);
        $hoursWorked      = $this->computeHoursWorked($timeInAm, $timeOutAm, $timeInPm, $timeOutPm, $isOvernight);
        $lateMinutes      = $this->computeLateMinutes($timeInAm, $timeInPm, $date, $schedule);
        $undertimeMinutes = $this->computeUndertimeMinutes($timeOutAm, $timeOutPm, $date, $schedule);
        $overtimeMinutes  = $this->computeOvertimeMinutes($timeOutPm, $date, $schedule);

        // Re-derive attendance status unless it is a protected status that was
        // set during generation (on_leave, holiday, on_official_business, wfh).
        $protectedStatuses = ['on_leave', 'holiday', 'on_official_business', 'wfh'];
        if (! in_array($record->attendance_status, $protectedStatuses)) {
            $hasAnyPunch = $timeInAm || $timeOutAm || $timeInPm || $timeOutPm;
            if (! $hasAnyPunch) {
                $attendanceStatus = 'absent';
            } elseif ($timeInAm && $timeOutPm) {
                // CSC rule: AM arrival + PM departure = full day present
                $attendanceStatus = 'present';
            } elseif (
                $hoursWorked < ($schedule?->half_day_hours ?? 4) ||
                $lateMinutes >= ($schedule?->late_threshold_minutes ?? 240)
            ) {
                $attendanceStatus = 'half_day';
            } else {
                $attendanceStatus = 'present';
            }
        } else {
            $attendanceStatus = $record->attendance_status;
        }

        $record->update([
            'schedule_id'       => $record->schedule_id,
            'hours_worked'      => round($hoursWorked, 2),
            'late_minutes'      => round($lateMinutes, 2),
            'undertime_minutes' => round($undertimeMinutes, 2),
            'overtime_minutes'  => round($overtimeMinutes, 2),
            'attendance_status' => $attendanceStatus,
        ]);
    }

    /**
     * Recompute late/undertime/overtime for all unlocked records of a user
     * in a given month (or date range) after a schedule has been assigned/changed.
     */
    public function recomputeForUser(int $userId, string $dateFrom, string $dateTo): int
    {
        $records = DtrRecord::where('user_id', $userId)
            ->where('is_locked', false)
            ->whereBetween('work_date', [$dateFrom, $dateTo])
            ->with('schedule')
            ->get();

        foreach ($records as $record) {
            $this->recompute($record->fresh());
        }

        return $records->count();
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

    /**
     * Collect biometric logs that belong to the shift starting on $dateStr.
     *
     * For regular (non-overnight) shifts: all logs on $dateStr.
     * For overnight shifts (time_out < time_in, e.g. 22:00–06:00):
     *   - Logs from $dateStr within [time_in - 2h, midnight)
     *   - Logs from $dateStr+1 within [midnight, time_out + 2h]
     *
     * The ±2h buffer absorbs early arrivals and late departures without
     * accidentally picking up the NEXT shift's punches.
     */
    private function getLogsForShift(
        \Illuminate\Support\Collection $allLogs,
        string $dateStr,
        ?EmployeeSchedule $schedule
    ): \Illuminate\Support\Collection {
        if (! $schedule || ! $schedule->isOvernightShift($dateStr)) {
            // Regular shift: all logs on this calendar date
            return $allLogs->filter(
                fn ($log) => Carbon::parse($log->log_datetime)->toDateString() === $dateStr
            )->values();
        }

        // Overnight shift: collect logs from both the start date and the next day
        $timeIn  = $schedule->getTimeIn($dateStr);
        $timeOut = $schedule->getTimeOut($dateStr);

        $shiftStart = Carbon::parse($dateStr . ' ' . $timeIn)->subHours(2);
        $nextDate   = Carbon::parse($dateStr)->addDay()->toDateString();
        $shiftEnd   = Carbon::parse($nextDate . ' ' . $timeOut)->addHours(2);

        return $allLogs->filter(function ($log) use ($shiftStart, $shiftEnd) {
            $dt = Carbon::parse($log->log_datetime);
            return $dt->gte($shiftStart) && $dt->lte($shiftEnd);
        })->values();
    }

    /**
     * Parse punches for an overnight shift (e.g. 22:00–06:00).
     *
     * Overnight shifts have no lunch break — we use a simple 2-punch model:
     *   first punch = time_in_am (shift start)
     *   last punch  = time_out_pm (shift end, next calendar day)
     *
     * Middle punches are ignored (no lunch break detection for overnight).
     * time_out_am and time_in_pm are always null for overnight shifts.
     */
    private function parsePunchesOvernight(
        \Illuminate\Support\Collection $logs,
        string $dateStr,
        EmployeeSchedule $schedule
    ): array {
        if ($logs->isEmpty()) {
            return [null, null, null, null];
        }

        $sorted  = $logs->sortBy('log_datetime')->values();
        $timeIn  = $schedule->getTimeIn($dateStr);
        $timeOut = $schedule->getTimeOut($dateStr);

        // Build the expected shift window with ±2h buffer
        $nextDate    = Carbon::parse($dateStr)->addDay()->toDateString();
        $windowStart = Carbon::parse($dateStr . ' ' . $timeIn)->subHours(2);
        $windowEnd   = Carbon::parse($nextDate . ' ' . $timeOut)->addHours(2);

        // Filter to only punches within the shift window
        $inWindow = $sorted->filter(function ($log) use ($windowStart, $windowEnd) {
            $dt = Carbon::parse($log->log_datetime);
            return $dt->gte($windowStart) && $dt->lte($windowEnd);
        })->values();

        if ($inWindow->isEmpty()) {
            return [null, null, null, null];
        }

        // First punch = time_in, last punch = time_out
        // Store as H:i:s — the date component is intentionally dropped here
        // because dtr_records.time_in_am/time_out_pm are TIME columns.
        // The date context is preserved via work_date (the shift start date).
        $timeInAm  = Carbon::parse($inWindow->first()->log_datetime)->format('H:i:s');
        $timeOutPm = Carbon::parse($inWindow->last()->log_datetime)->format('H:i:s');

        // If only one punch, treat as time-in only (employee forgot to tap out)
        if ($inWindow->count() === 1) {
            return [$timeInAm, null, null, null];
        }

        return [$timeInAm, null, null, $timeOutPm];
    }

    private function parsePunches(
        \Illuminate\Support\Collection $logs,
        string $dateStr,
        ?EmployeeSchedule $schedule
    ): array {
        if ($logs->isEmpty()) {
            return [null, null, null, null];
        }

        // ── Overnight shift: bypass all noon-based heuristics ────────────────────
        if ($schedule && $schedule->isOvernightShift($dateStr)) {
            return $this->parsePunchesOvernight($logs, $dateStr, $schedule);
        }

        $sorted = $logs->sortBy('log_datetime')->values();
        $count  = $sorted->count();

        // Precompute time-of-day minutes and formatted time strings for each log.
        $mins  = $sorted->map(fn ($l) => $this->logMinutes($l))->values()->all();
        $times = $sorted->map(fn ($l) => Carbon::parse($l->log_datetime)->format('H:i:s'))->values()->all();

        // Break midpoint (12:30 default for standard PH shifts) and PM-in cutoff.
        $breakMinutes  = $this->getBreakMinutes($dateStr, $schedule);
        $pmInCutoffMin = $breakMinutes + 90; // latest a post-lunch return can occur (e.g. 14:00)

        // ── Single punch ──────────────────────────────────────────────────────
        // Noon (12:00 = 720 min) is the hard cutoff for AM_IN:
        // a single punch at or after noon is a PM arrival, never a morning arrival.
        if ($count === 1) {
            if ($mins[0] < 720) {
                return [$times[0], null, null, null]; // morning arrival only
            }
            if ($mins[0] <= $pmInCutoffMin) {
                return [null, null, $times[0], null]; // PM half-day arrival
            }
            return [null, null, null, $times[0]];     // end-of-day departure only
        }

        // ── No morning session: first punch is at noon or later ───────────────
        // The employee has no morning biometric activity (e.g. approved AM leave,
        // late/afternoon-only arrival).  A first punch at or after noon is NEVER
        // placed in AM_IN regardless of the schedule's break midpoint.
        if ($mins[0] >= 720) {
            // With 3+ punches where both first punches are within the PM-in window:
            // the first two may form a short break pattern (AM_OUT + PM_IN).
            if ($count >= 3 && $mins[0] <= $pmInCutoffMin && $mins[1] <= $pmInCutoffMin && $mins[1] > $mins[0]) {
                return [null, $times[0], $times[1], $times[$count - 1]]; // AM_OUT + PM_IN + PM_OUT
            }
            // All other PM-only sessions: first punch is PM-in, last punch is PM-out.
            // Covers employees arriving after $pmInCutoffMin (e.g. approved half-day AM leave at 14:30).
            return [null, null, $times[0], $times[$count - 1]];
        }

        // ── 2+ punches with an AM arrival ─────────────────────────────────────
        // The first punch is always AM-in; the last punch is always PM-out.
        // We never use the device's io_status code because employees frequently
        // press the wrong button (e.g. Break-Out instead of Check-In).
        $timeInAm  = $times[0];
        $timeOutPm = $times[$count - 1];
        $timeOutAm = null;
        $timeInPm  = null;

        // Two punches → full day with no lunch-break data available.
        if ($count === 2) {
            return [$timeInAm, null, null, $timeOutPm];
        }

        // ── Schedule-relative AM-out window ───────────────────────────────────
        // A punch qualifies as a lunch departure only if it falls within:
        //   [scheduledStart + 60 min, breakMidpoint + 30 min]
        // e.g. [09:00, 13:00] for a standard 08:00 shift.
        // The AM session must also be at least 30 min long.
        $scheduledInMin = 480; // 08:00 default
        if ($schedule) {
            $ti = $schedule->getTimeIn($dateStr);
            if ($ti) {
                $scheduledInMin = $this->timeStrToMinutes(substr($ti, 0, 5));
            }
        }
        $amOutMinMin = $scheduledInMin + 60;  // e.g. 09:00 for an 08:00 shift
        $amOutMaxMin = $breakMinutes + 30;    // e.g. 13:00 for the standard 12:30 break

        // ── Phase 1: scan middle punches for a lunch-break pair ───────────────
        // Previous approach: split all punches at 12:30 into AM / PM buckets.
        // Problem: when AM-out (e.g. 12:05) and PM-in (e.g. 12:08) are both
        // before 12:30 they both land in the AM bucket — the last AM punch
        // becomes time_out_am and the actual PM-in is lost.
        //
        // New approach: scan chronologically through middle punches (indices
        // 1 … count−2).  The first punch that falls within the AM-out window
        // (and satisfies the minimum AM session length) is assigned as AM-out.
        // If the immediately following middle punch is also within the PM-in
        // window it becomes PM-in — regardless of whether both are before or
        // after 12:30.  This correctly handles short lunch gaps of even 1 min.
        $lunchFound = false;
        for ($i = 1; $i <= $count - 2; $i++) {
            $outMin  = $mins[$i];
            $amInMin = $this->timeStrToMinutes(substr($timeInAm, 0, 5));

            if ($outMin < $amOutMinMin || $outMin > $amOutMaxMin) {
                continue; // outside the valid departure window
            }
            if ($outMin - $amInMin < 30) {
                continue; // AM session too short to be a real lunch departure
            }

            $nextIdx = $i + 1;

            // If this is the only remaining middle punch and it falls at or after
            // the break midpoint, it is more likely a PM-in return than a lunch
            // departure — skip it so Phase 2 can assign it as time_in_pm.
            if ($nextIdx > $count - 2 && $outMin >= $breakMinutes) {
                continue;
            }

            $timeOutAm  = $times[$i];
            $lunchFound = true;

            // Scan forward through remaining middle punches for the first one in a
            // strictly later minute than AM-out. A plain i+1 check fails when the
            // employee taps the reader multiple times in rapid succession — the punch
            // immediately after AM-out may be a same-minute duplicate of that action,
            // so we skip same-minute punches to reach the actual PM-in tap.
            for ($j = $i + 1; $j <= $count - 2; $j++) {
                if ($mins[$j] > $outMin) {
                    $timeInPm = $times[$j];
                    break;
                }
            }
            break; // stop at the first valid AM-out
        }

        // ── Phase 2: standalone PM return (no AM-out found) ──────────────────
        // Employee has a biometric gap at lunch departure but tapped back in.
        // E.g. [08:00, 13:30, 17:00] → time_in_pm = 13:30.
        // No upper-time ceiling: the loop boundary ($i <= $count-2) already
        // ensures the punch is not the final PM-out, and >= $breakMinutes
        // excludes morning punches. Any remaining middle punch after the break
        // midpoint is the best available PM-in candidate.
        if (! $lunchFound) {
            for ($i = 1; $i <= $count - 2; $i++) {
                if ($mins[$i] >= $breakMinutes) {
                    $timeInPm = $times[$i];
                    break;
                }
            }
        }

        // ── Sanity-check ordering ─────────────────────────────────────────────
        if ($timeInAm && $timeOutAm && $timeOutAm <= $timeInAm) {
            $timeOutAm = null;
        }
        if ($timeInPm && $timeOutPm && $timeOutPm <= $timeInPm) {
            $timeOutPm = null;
        }

        return [$timeInAm, $timeOutAm, $timeInPm, $timeOutPm];
    }

    /**
     * Noon break midpoint in minutes from midnight.
     * Used to:
     *   • detect PM-only sessions (first punch at or after this point)
     *   • derive amOutMaxMin  (breakMinutes + 30, e.g. 13:00)
     *   • derive pmInCutoffMin (breakMinutes + 90, e.g. 14:00)
     *   • find standalone PM-in punches (Phase 2 of parsePunches)
     * Defaults to 12:30 (750 min) for standard PH shifts.
     * For non-standard shifts whose midpoint falls outside 11:00–13:30
     * the schedule midpoint is used instead.
     */
    private function getBreakMinutes(string $dateStr, ?EmployeeSchedule $schedule): int
    {
        // Overnight shifts have no lunch break
        if ($schedule && $schedule->isOvernightShift($dateStr)) {
            return 0;
        }

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
        ?string $timeOutPm,
        bool $isOvernight = false
    ): float {
        $inAm  = $this->floorToMinute($timeInAm);
        $outAm = $this->floorToMinute($timeOutAm);
        $inPm  = $this->floorToMinute($timeInPm);
        $outPm = $this->floorToMinute($timeOutPm);

        // ── All 4 punches: both sessions are fully known ──────────────────────
        if ($inAm && $outAm && $inPm && $outPm) {
            return (
                Carbon::parse($inAm)->diffInMinutes(Carbon::parse($outAm)) +
                Carbon::parse($inPm)->diffInMinutes(Carbon::parse($outPm))
            ) / 60;
        }

        // ── AM in + AM out + PM out, no PM in ─────────────────────────────────
        // AM session is known; estimate PM start as AM out + 60 min lunch.
        if ($inAm && $outAm && ! $inPm && $outPm) {
            $amMinutes    = Carbon::parse($inAm)->diffInMinutes(Carbon::parse($outAm));
            $pmStart      = Carbon::parse($outAm)->addMinutes(60);
            $pmMinutes    = max(0, $pmStart->diffInMinutes(Carbon::parse($outPm), false));
            return ($amMinutes + $pmMinutes) / 60;
        }

        // ── AM in + PM out (no AM out), with or without PM in ─────────────────
        // No AM-out means we can't split sessions — use 2-punch formula.
        if ($inAm && $outPm && ! $outAm) {
            $inCarbon  = Carbon::parse($inAm);
            $outCarbon = Carbon::parse($outPm);
            // For overnight shifts, time_out is on the next day
            if ($isOvernight && $outCarbon->lt($inCarbon)) {
                $outCarbon->addDay();
            }
            $total = $inCarbon->diffInMinutes($outCarbon);
            // No lunch deduction for overnight shifts (no break midpoint)
            return $isOvernight ? $total / 60 : max(0, $total - 60) / 60;
        }

        // ── AM session only ───────────────────────────────────────────────────
        if ($inAm && $outAm) {
            return Carbon::parse($inAm)->diffInMinutes(Carbon::parse($outAm)) / 60;
        }

        // ── PM session only ───────────────────────────────────────────────────
        if ($inPm && $outPm) {
            return Carbon::parse($inPm)->diffInMinutes(Carbon::parse($outPm)) / 60;
        }

        return 0;
    }

    /**
     * Total late minutes = AM-in late + PM-in late (returning late from lunch).
     * Seconds are discarded; only full minutes are counted.
     */
    private function computeLateMinutes(
        ?string $timeInAm,
        ?string $timeInPm,
        string $dateStr,
        ?EmployeeSchedule $schedule
    ): float {
        if (! $schedule) {
            return 0;
        }

        $grace        = max(0, (int) ($schedule->grace_period_minutes ?? 15));
        $breakMinutes = $this->getBreakMinutes($dateStr, $schedule);
        $late         = 0.0;

        // ── AM in late ────────────────────────────────────────────────────────
        if ($timeInAm) {
            $scheduledTimeIn = $schedule->getTimeIn($dateStr);
            if ($scheduledTimeIn) {
                $scheduledIn = Carbon::parse($dateStr . ' ' . $scheduledTimeIn);
                $actualIn    = Carbon::parse($dateStr . ' ' . $this->floorToMinute($timeInAm));

                if ($actualIn->gt($scheduledIn)) {
                    $graceDeadline = $scheduledIn->copy()->addMinutes($grace);
                    if ($actualIn->gt($graceDeadline)) {
                        $late += $scheduledIn->diffInMinutes($actualIn);
                    }
                }
            }
        }

        // ── PM in late (returned late from lunch) ─────────────────────────────
        // No grace period: any minute past the expected return time is counted.
        // Expected PM start = break midpoint + 30 min (e.g. 12:30+30 = 13:00).
        // Skip for overnight shifts — they have no lunch break.
        if ($timeInPm && (! $schedule || ! $schedule->isOvernightShift($dateStr))) {
            $lunchEnd    = Carbon::parse($dateStr)->startOfDay()->addMinutes($breakMinutes + 30);
            $actualInPm  = Carbon::parse($dateStr . ' ' . $this->floorToMinute($timeInPm));

            if ($actualInPm->gt($lunchEnd)) {
                $late += $lunchEnd->diffInMinutes($actualInPm);
            }
        }

        return $late;
    }

    /**
     * Total undertime minutes = AM-out undertime (left early for lunch)
     *                         + PM-out undertime (left before end of day).
     * Seconds are discarded; only full minutes are counted.
     */
    private function computeUndertimeMinutes(
        ?string $timeOutAm,
        ?string $timeOutPm,
        string $dateStr,
        ?EmployeeSchedule $schedule
    ): float {
        if (! $schedule) {
            return 0;
        }

        $breakMinutes = $this->getBreakMinutes($dateStr, $schedule);
        $undertime    = 0.0;

        // ── AM out undertime (left before lunch started) ──────────────────────
        // Expected AM end = break midpoint − 30 min (e.g. 12:30−30 = 12:00).
        // Skip for overnight shifts — they have no lunch break.
        if ($timeOutAm && (! $schedule || ! $schedule->isOvernightShift($dateStr))) {
            $lunchStart   = Carbon::parse($dateStr)->startOfDay()->addMinutes($breakMinutes - 30);
            $actualOutAm  = Carbon::parse($dateStr . ' ' . $this->floorToMinute($timeOutAm));

            if ($actualOutAm->lt($lunchStart)) {
                $undertime += $actualOutAm->diffInMinutes($lunchStart);
            }
        }

        // ── PM out undertime (left before end of day) ─────────────────────────
        if ($timeOutPm) {
            $scheduledTimeOut = $schedule->getTimeOut($dateStr);
            if ($scheduledTimeOut) {
                $scheduledOut = Carbon::parse($dateStr . ' ' . $scheduledTimeOut);
                // For overnight shifts, time_out is on the next calendar day
                if ($schedule->isOvernightShift($dateStr)) {
                    $scheduledOut->addDay();
                }
                $actualOutPm = Carbon::parse($dateStr . ' ' . $this->floorToMinute($timeOutPm));
                // For overnight, actual out may also be next day
                if ($schedule->isOvernightShift($dateStr) && $actualOutPm->lt(Carbon::parse($dateStr . ' 12:00:00'))) {
                    $actualOutPm->addDay();
                }
                if ($actualOutPm->lt($scheduledOut)) {
                    $undertime += $actualOutPm->diffInMinutes($scheduledOut);
                }
            }
        }

        return $undertime;
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
        if ($schedule->isOvernightShift($dateStr)) {
            $scheduledOut->addDay();
        }
        $actualOut = Carbon::parse($dateStr . ' ' . $this->floorToMinute($timeOutPm));
        if ($schedule->isOvernightShift($dateStr) && $actualOut->lt(Carbon::parse($dateStr . ' 12:00:00'))) {
            $actualOut->addDay();
        }

        if ($actualOut->lte($scheduledOut)) {
            return 0;
        }

        return $actualOut->diffInMinutes($scheduledOut);
    }

    /**
     * Truncate a time string to minute precision, discarding seconds.
     * '08:00:59' → '08:00:00',  '17:03:22' → '17:03:00'
     */
    private function floorToMinute(?string $time): ?string
    {
        if (! $time) {
            return null;
        }
        [$h, $m] = explode(':', $time);
        return sprintf('%02d:%02d:00', (int) $h, (int) $m);
    }

    private function getAttendanceStatus(
        \Illuminate\Support\Collection $logs,
        mixed $leave,
        string $dayType,
        float $hoursWorked,
        float $lateMinutes,
        ?EmployeeSchedule $schedule,
        ?string $timeInAm = null,
        ?string $timeOutPm = null
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

        // CSC Form 48 rule: AM arrival + PM departure = full day present.
        // This takes precedence over the hours-based threshold so that employees
        // who tap in once in the morning and once in the afternoon are not
        // incorrectly flagged as half-day due to schedule mis-configuration.
        if ($timeInAm && $timeOutPm) {
            return 'present';
        }

        $halfDayThreshold = $schedule ? ($schedule->half_day_hours ?? 4) : 4;

        if ($hoursWorked < $halfDayThreshold || $lateMinutes >= ($schedule->late_threshold_minutes ?? 240)) {
            return 'half_day';
        }

        return 'present';
    }
}
