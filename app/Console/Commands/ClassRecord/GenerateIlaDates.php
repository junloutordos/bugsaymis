<?php

namespace App\Console\Commands\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordIlaDate;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateIlaDates extends Command
{
    protected $signature = 'class-record:generate-ila-dates
                            {--date= : Generate for a single target date (Y-m-d) instead of the rolling window.}
                            {--days=10 : When no --date is given, how many days ahead (inclusive of today) to generate.}';

    protected $description = 'Auto-create ILA date entries for every class record whose subject has a scheduled ILP period, generated ahead of time so the graded/non-graded decision can be locked in by the WAT plotting deadline (12:00 NN the Friday before).';

    public function handle(): int
    {
        $dates = $this->option('date')
            ? collect([Carbon::parse($this->option('date'))])
            : collect(range(0, max(0, (int) $this->option('days') - 1)))
                ->map(fn ($offset) => Carbon::today()->addDays($offset));

        $term = AcademicTerm::where('is_current', true)->first();
        if (! $term) {
            $this->info('No current academic term — nothing to do.');

            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($dates as $date) {
            [$created, $skipped] = $this->generateForDate($date, $term);
            $totalCreated += $created;
            $totalSkipped += $skipped;
        }

        $this->info("ILA dates generated across {$dates->count()} day(s): {$totalCreated} class record(s) processed, {$totalSkipped} skipped (no class record yet or ambiguous active quarter).");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} [created, skipped] */
    private function generateForDate(Carbon $date, AcademicTerm $term): array
    {
        $dayOfWeek = $date->format('l'); // "Monday", "Tuesday", …

        $schedules = ClassSchedule::ilp()
            ->occupying()
            ->forTerm($term->id)
            ->onDay($dayOfWeek)
            ->whereHas('subject', fn ($q) => $q->where('has_ilp', true))
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($schedules as $schedule) {
            $classRecord = ClassRecord::where('subject_id', $schedule->subject_id)
                ->where('section_id', $schedule->section_id)
                ->where('teacher_id', $schedule->user_id)
                ->where('school_year_id', $term->school_year_id)
                ->where('status', '<>', 'archived')
                ->first();

            if (! $classRecord) {
                $skipped++;
                continue;
            }

            $quarter = $this->activeQuarterFor($classRecord);
            if (! $quarter) {
                $skipped++;
                continue;
            }

            $maxOrder = ClassRecordIlaDate::where('class_record_quarter_id', $quarter->id)
                ->max('sort_order') ?? 0;

            ClassRecordIlaDate::firstOrCreate(
                ['class_record_quarter_id' => $quarter->id, 'date' => $date->toDateString()],
                ['sort_order' => $maxOrder + 1, 'is_auto_generated' => true]
            );
            $created++;
        }

        return [$created, $skipped];
    }

    /**
     * The single unlocked quarter for this class record — the one currently
     * in progress. If no quarters exist yet (teacher hasn't opened the record)
     * or more than one is unlocked at once, there's nothing safe to target.
     */
    private function activeQuarterFor(ClassRecord $classRecord): ?ClassRecordQuarter
    {
        $quarters = ClassRecordQuarter::where('class_record_id', $classRecord->id)->get();
        $unlocked = $quarters->where('is_locked', false);

        return $unlocked->count() === 1 ? $unlocked->first() : null;
    }
}
