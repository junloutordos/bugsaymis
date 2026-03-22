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

            // If it's a rest day with no punches and no special conditions — skip
            $logsForDay = $allLogs->get($dateStr, collect());
            if ($dayType === 'rest_day' && $logsForDay->isEmpty()) {
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

        $hoursWorked      = $this->computeHoursWorked(
            $record->time_in_am, $record->time_out_am,
            $record->time_in_pm, $record->time_out_pm
        );
        $lateMinutes      = $this->computeLateMinutes($record->time_in_am, $date, $schedule);
        $undertimeMinutes = $this->computeUndertimeMinutes($record->time_out_pm, $date, $schedule);
        $overtimeMinutes  = $this->computeOvertimeMinutes($record->time_out_pm, $date, $schedule);

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

        // If log types are explicitly set (not all 'auto'), use pair-matching
        $hasExplicit = $logs->contains(fn ($l) => $l->log_type !== 'auto');

        if ($hasExplicit) {
            $ins  = $logs->where('log_type', 'time_in')->sortBy('log_datetime')->values();
            $outs = $logs->where('log_type', 'time_out')->sortBy('log_datetime')->values();

            $timeInAm  = $ins->get(0)  ? Carbon::parse($ins->get(0)->log_datetime)->format('H:i:s')  : null;
            $timeOutAm = $outs->get(0) ? Carbon::parse($outs->get(0)->log_datetime)->format('H:i:s') : null;
            $timeInPm  = $ins->get(1)  ? Carbon::parse($ins->get(1)->log_datetime)->format('H:i:s')  : null;
            $timeOutPm = $outs->get(1) ? Carbon::parse($outs->get(1)->log_datetime)->format('H:i:s') : null;
        } else {
            // Sequential position mapping
            $sorted = $logs->sortBy('log_datetime')->values();

            $timeInAm  = $sorted->get(0) ? Carbon::parse($sorted->get(0)->log_datetime)->format('H:i:s') : null;
            $timeOutAm = $sorted->get(1) ? Carbon::parse($sorted->get(1)->log_datetime)->format('H:i:s') : null;
            $timeInPm  = $sorted->get(2) ? Carbon::parse($sorted->get(2)->log_datetime)->format('H:i:s') : null;
            $timeOutPm = $sorted->get(3) ? Carbon::parse($sorted->get(3)->log_datetime)->format('H:i:s') : null;

            // Extra punches → update time_out_pm if later
            if ($sorted->count() > 4) {
                $last = Carbon::parse($sorted->last()->log_datetime)->format('H:i:s');
                if ($last > $timeOutPm) {
                    $timeOutPm = $last;
                }
            }
        }

        return [$timeInAm, $timeOutAm, $timeInPm, $timeOutPm];
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

        $scheduledIn   = Carbon::parse($dateStr . ' ' . $schedule->time_in);
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

        $scheduledOut = Carbon::parse($dateStr . ' ' . $schedule->time_out);
        $actualOut    = Carbon::parse($dateStr . ' ' . $timeOutPm);

        return max(0, $scheduledOut->diffInMinutes($actualOut, false));
    }

    private function computeOvertimeMinutes(?string $timeOutPm, string $dateStr, ?EmployeeSchedule $schedule): float
    {
        if (! $timeOutPm || ! $schedule) {
            return 0;
        }

        $scheduledOut = Carbon::parse($dateStr . ' ' . $schedule->time_out);
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
