<?php

namespace App\Services\HR;

use App\Models\HR\BiometricLog;
use App\Models\HR\DtrRecord;
use App\Models\HR\EmployeeSchedule;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveApplication;
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

            // WFH fallback: when there are no biometric logs for this date but
            // the employee has a WFH attendance record, use WFH time_in / time_out
            // as the AM-in and PM-out slots so that hours & late/undertime compute.
            $wfh = $wfhAttendances->get($dateStr);
            if ($wfh && $logsForDay->isEmpty()) {
                $timeInAm  = $wfh->time_in  ? Carbon::parse($wfh->time_in)->format('H:i:s')  : null;
                $timeOutPm = $wfh->time_out ? Carbon::parse($wfh->time_out)->format('H:i:s') : null;
            }

            // Compute metrics
            $hoursWorked      = $this->computeHoursWorked($timeInAm, $timeOutAm, $timeInPm, $timeOutPm);
            $lateMinutes      = $this->computeLateMinutes($timeInAm, $timeInPm, $dateStr, $schedule);
            $undertimeMinutes = $this->computeUndertimeMinutes($timeOutAm, $timeOutPm, $dateStr, $schedule);
            $overtimeMinutes  = $this->computeOvertimeMinutes($timeOutPm, $dateStr, $schedule);

            // Determine attendance status
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
                    'processed_by'         => Auth::id(),
                    'processed_at'         => now(),
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

        $hoursWorked      = $this->computeHoursWorked($timeInAm, $timeOutAm, $timeInPm, $timeOutPm);
        $lateMinutes      = $this->computeLateMinutes($timeInAm, $timeInPm, $date, $schedule);
        $undertimeMinutes = $this->computeUndertimeMinutes($timeOutAm, $timeOutPm, $date, $schedule);
        $overtimeMinutes  = $this->computeOvertimeMinutes($timeOutPm, $date, $schedule);

        // Re-derive attendance status unless it is a protected status that was
        // set during generation (on_leave, holiday, on_official_business).
        $protectedStatuses = ['on_leave', 'holiday', 'on_official_business'];
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

    private function parsePunches(
        \Illuminate\Support\Collection $logs,
        string $dateStr,
        ?EmployeeSchedule $schedule
    ): array {
        if ($logs->isEmpty()) {
            return [null, null, null, null];
        }

        $sorted = $logs->sortBy('log_datetime')->values();

        // Split all punches into AM (before break midpoint) and PM (break midpoint and after).
        // 12:30 is the default split — midpoint of the standard PH noon break — so that a
        // lunch-departure punch at e.g. 12:02 stays in the AM bucket while a post-lunch
        // return at e.g. 12:45 lands in PM.
        $breakMinutes = $this->getBreakMinutes($dateStr, $schedule);

        $amLogs = $sorted->filter(fn ($l) => $this->logMinutes($l) < $breakMinutes)->values();
        $pmLogs = $sorted->filter(fn ($l) => $this->logMinutes($l) >= $breakMinutes)->values();

        // ── Schedule-relative time windows ────────────────────────────────────
        // Each employee's official schedule defines what counts as a valid
        // morning arrival (AM-in) vs a valid lunch departure (AM-out).
        // Defaults assume an 08:00 shift start when no schedule is assigned.
        //
        //   AM-in  cutoff : scheduled_start + 60 min  (09:00 for 08:00 shift)
        //                   Punches at or before this mark the start of the day.
        //                   Punches after it are treated as late arrivals if no
        //                   earlier morning punch exists.
        //
        //   AM-out window : [scheduled_start + 60 min, 13:00]
        //                   (09:00–13:00 for 08:00 shift)
        //                   A punch can only be a lunch departure if it falls
        //                   within this window AND the AM session is ≥ 30 min.
        $scheduledInMin = 480; // 08:00 default
        if ($schedule) {
            $ti = $schedule->getTimeIn($dateStr);
            if ($ti) {
                $scheduledInMin = $this->timeStrToMinutes(substr($ti, 0, 5));
            }
        }
        $amInCutoffMin = $scheduledInMin + 60; // e.g. 09:00 for 08:00 shift
        $amOutMinMin   = $scheduledInMin + 60; // earliest valid lunch departure
        $amOutMaxMin   = 780;                  // 13:00 hard ceiling

        // ── Position-based punch assignment ───────────────────────────────────
        // We intentionally ignore log_type (the io_status code the device reports)
        // because employees frequently press the wrong button (e.g. Break-Out
        // instead of Check-In for their morning arrival).  Chronological position
        // within each time-of-day bucket is a more reliable signal of intent.
        //
        // AM bucket is further split at the AM-in cutoff:
        //   • Punches ≤ amInCutoffMin → "expected morning window" (pre-cutoff)
        //   • Punches >  amInCutoffMin → late arrivals (post-cutoff)
        //
        // time_in_am  = first punch in pre-cutoff window; falls back to first
        //               late-AM punch when no pre-cutoff punch exists (genuine
        //               very-late arrival — still captured as AM-in so that
        //               lateness is computed correctly).
        //
        // time_out_am = last AM punch, accepted only when it falls within the
        //               AM-out window AND the resulting AM session is ≥ 30 min
        //               (prevents early double-tap noise from being treated as
        //               a lunch departure).
        $earlyAmLogs = $amLogs->filter(fn ($l) => $this->logMinutes($l) <= $amInCutoffMin)->values();

        $timeInAm = $earlyAmLogs->isNotEmpty()
            ? $this->firstTime($earlyAmLogs)   // normal morning arrival
            : $this->firstTime($amLogs);        // late arrival — no pre-cutoff punch

        $timeOutAm = null;
        if ($amLogs->count() >= 2) {
            $lastAm = $this->lastTime($amLogs);
            $outMin = $this->timeStrToMinutes(substr($lastAm, 0, 5));
            $inMin  = $timeInAm ? $this->timeStrToMinutes(substr($timeInAm, 0, 5)) : 0;

            $inWindow   = $outMin >= $amOutMinMin && $outMin <= $amOutMaxMin;
            $longEnough = ($outMin - $inMin) >= 30; // AM session must be at least 30 min

            if ($inWindow && $longEnough) {
                $timeOutAm = $lastAm;
            }
        }

        // ── PM-in cutoff ──────────────────────────────────────────────────────
        // A post-lunch return punch must happen within a reasonable window after
        // the break midpoint.  Any punch after this cutoff is clearly an
        // end-of-day departure, not a return from lunch.
        //
        //   PM-in cutoff = break midpoint + 90 min  (14:00 for 12:30 midpoint)
        //
        // This prevents 16:00 / 17:00 punches from being mis-classified as
        // time_in_pm when e.g. an employee taps twice at the end of the day.
        $pmInCutoffMin = $breakMinutes + 90; // e.g. 14:00 for standard schedule

        // ── PM punch assignment (position-based + schedule-aware) ─────────────
        if ($pmLogs->count() >= 2) {
            $firstPmMin = $this->logMinutes($pmLogs->first());

            if ($firstPmMin <= $pmInCutoffMin) {
                // First PM punch is within the post-lunch return window:
                // first = return from lunch, last = end of day
                $timeInPm  = $this->firstTime($pmLogs);
                $timeOutPm = $this->lastTime($pmLogs);
            } else {
                // First PM punch is too late to be a post-lunch return
                // (e.g. 16:00 / 17:00) — all PM punches are end-of-day taps.
                $timeInPm  = null;
                $timeOutPm = $this->lastTime($pmLogs);
            }
        } elseif ($pmLogs->count() === 1 && $amLogs->isNotEmpty()) {
            // Single PM punch with AM activity → end-of-day departure only
            $timeInPm  = null;
            $timeOutPm = $this->firstTime($pmLogs);
        } elseif ($pmLogs->count() === 1) {
            $firstPmMin = $this->logMinutes($pmLogs->first());

            if ($firstPmMin <= $pmInCutoffMin) {
                // Within the post-lunch return window and no AM session:
                // employee arrived for a PM-only half day
                $timeInPm  = $this->firstTime($pmLogs);
                $timeOutPm = null;
            } else {
                // After the cutoff with no AM session: treat as end-of-day
                // departure (no PM-in recorded, hours will be 0 unless AM exists)
                $timeInPm  = null;
                $timeOutPm = $this->firstTime($pmLogs);
            }
        } else {
            $timeInPm  = null;
            $timeOutPm = null;
        }

        // ── Sanity-check ordering ─────────────────────────────────────────────
        // If out ≤ in it indicates a bounce / double-punch artifact.
        // Clear the invalid out rather than storing an impossible sequence.
        if ($timeInAm && $timeOutAm && $timeOutAm <= $timeInAm) {
            $timeOutAm = null;
        }
        if ($timeInPm && $timeOutPm && $timeOutPm <= $timeInPm) {
            $timeOutPm = null;
        }

        // ── Negligible lunch-break correction ────────────────────────────────
        // If the gap between AM-out and PM-in is less than 15 minutes the
        // employee likely tapped the device multiple times in quick succession
        // (or mis-pressed a button immediately after), producing a near-zero
        // "break".  Drop both split points and treat the day as a continuous
        // session so hours are computed correctly.
        if ($timeOutAm && $timeInPm) {
            $outAmMin = $this->timeStrToMinutes(substr($timeOutAm, 0, 5));
            $inPmMin  = $this->timeStrToMinutes(substr($timeInPm, 0, 5));
            if ($inPmMin - $outAmMin < 15) {
                $timeOutAm = null;
                $timeInPm  = null;
            }
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
            $total = Carbon::parse($inAm)->diffInMinutes(Carbon::parse($outPm));
            return max(0, $total - 60) / 60;
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
        if ($timeInPm) {
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
        if ($timeOutAm) {
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
                $actualOutPm  = Carbon::parse($dateStr . ' ' . $this->floorToMinute($timeOutPm));

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
        $actualOut    = Carbon::parse($dateStr . ' ' . $this->floorToMinute($timeOutPm));

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
