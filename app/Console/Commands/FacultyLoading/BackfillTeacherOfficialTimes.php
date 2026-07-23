<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\HR\EmployeeSchedule;
use App\Services\FacultyLoading\TeacherOfficialTimeSyncService;
use Illuminate\Console\Command;

/**
 * One-time backfill: sync teacher_official_times from every employee's
 * currently active, HR-approved employee_schedules record.
 *
 * Needed because the approve/assign sync (added 2026-07-23) only fires going
 * forward — employees whose schedule was approved before that change would
 * otherwise stay on the teacher_official_times default (or a stale row)
 * until their next HR schedule action. Safe to re-run any time; each run
 * simply re-upserts from whatever is currently the active default schedule.
 */
class BackfillTeacherOfficialTimes extends Command
{
    protected $signature = 'faculty-loading:backfill-official-times';

    protected $description = 'Sync teacher_official_times from every employee\'s active HR-approved work schedule';

    public function handle(TeacherOfficialTimeSyncService $sync): int
    {
        $schedules = EmployeeSchedule::where('is_default', true)
            ->where('status', 'approved')
            ->whereNull('end_date')
            ->get();

        if ($schedules->isEmpty()) {
            $this->line('No active approved employee schedules found.');
            return self::SUCCESS;
        }

        $this->info("Syncing official time + lunch for {$schedules->count()} employee(s)...");

        $bar = $this->output->createProgressBar($schedules->count());
        $bar->start();

        foreach ($schedules as $schedule) {
            $sync->syncFromEmployeeSchedule($schedule);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
