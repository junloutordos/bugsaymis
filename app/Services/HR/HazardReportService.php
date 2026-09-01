<?php

namespace App\Services\HR;

use App\Models\HR\DtrRecord;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Computes Hazard Actual Exposure days per Plantilla employee for a date
 * range — the basis for Hazard Pay. A day counts as 1 full day at >=6
 * effective hours present, 0.5 day at 4-6 hours, and is not counted below 4.
 *
 * "Effective hours" excludes gate-pass deducted time (not actual on-site
 * presence). Lunch break needs no separate handling — DTRService already
 * excludes it from the stored hours_worked for every punch pattern.
 *
 * WFH days and official-travel days never count (hazard pay compensates
 * physical on-site exposure, not off-site work). A day involving any
 * self-declared "penned" data only counts on the strength of its VERIFIED
 * (biometric/online) punches alone until HR approves the penned entry
 * (penned_reviewed_at set) — e.g. a punched 07:41-17:00 day with just a
 * self-declared lunch gap already clears the threshold without the penned
 * data, so it counts; a day that only reaches the threshold BECAUSE of the
 * penned data stays at 0 until reviewed. This is what makes
 * DtrRecordController's approvePenned() action consequential for pay, not
 * just a status marker, for days that don't already clear the bar on their
 * own.
 */
class HazardReportService
{
    private const FULL_DAY_THRESHOLD = 6.0;

    private const HALF_DAY_THRESHOLD = 4.0;

    public function __construct(private DTRService $dtrService)
    {
    }

    /**
     * @return Collection<int, array{
     *     user_id: int,
     *     name: string,
     *     emp_category: string,
     *     full_days: int,
     *     half_days: int,
     *     total_hazard_days: float,
     * }>
     */
    public function generate(string $dateFrom, string $dateTo): Collection
    {
        $users = User::employees()
            ->where('status', 'active')
            ->whereIn('emp_category', ['Plantilla Teaching', 'Plantilla Non-Teaching'])
            ->orderBy('name')
            ->get(['id', 'name', 'emp_category']);

        $recordsByUser = DtrRecord::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('work_date', [$dateFrom, $dateTo])
            ->get()
            ->groupBy('user_id');

        return $users->map(function (User $user) use ($recordsByUser) {
            $fullDays = 0;
            $halfDays = 0;

            foreach ($recordsByUser->get($user->id, collect()) as $record) {
                $weight = $this->dayWeight($record);

                if ($weight === 1.0) {
                    $fullDays++;
                } elseif ($weight === 0.5) {
                    $halfDays++;
                }
            }

            return [
                'user_id'           => $user->id,
                'name'              => $user->name,
                'emp_category'      => $user->emp_category,
                'full_days'         => $fullDays,
                'half_days'         => $halfDays,
                'total_hazard_days' => $fullDays + ($halfDays * 0.5),
            ];
        })->values();
    }

    private function dayWeight(DtrRecord $record): float
    {
        if ($record->attendance_status === 'wfh' || $record->is_travel) {
            return 0.0;
        }

        $hasPennedData = $record->penned_time_in_am || $record->penned_time_out_am
            || $record->penned_time_in_pm || $record->penned_time_out_pm
            || $record->penned_submitted_at;

        $hoursWorked = (float) $record->hours_worked;
        if ($hasPennedData && ! $record->penned_reviewed_at) {
            // Don't credit the pending self-declared portion — only the
            // verified punches count until HR reviews it.
            $hoursWorked = $this->dtrService->computeVerifiedHoursWorked($record);
        }

        $effectiveHours = max(0, $hoursWorked - ((float) $record->gatepass_deduction_minutes / 60));

        if ($effectiveHours >= self::FULL_DAY_THRESHOLD) {
            return 1.0;
        }

        if ($effectiveHours >= self::HALF_DAY_THRESHOLD) {
            return 0.5;
        }

        return 0.0;
    }
}
