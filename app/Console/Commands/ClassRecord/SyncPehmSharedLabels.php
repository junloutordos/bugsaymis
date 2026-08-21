<?php

namespace App\Console\Commands\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use Illuminate\Console\Command;

class SyncPehmSharedLabels extends Command
{
    protected $signature = 'class-record:sync-pehm-shared-labels {--commit : Actually write changes; without this flag the command only reports what it would do}';

    protected $description = 'One-off data fix: re-derive subject_name (via ClassRecord::syncSharedDisplayLabel()) on every non-archived shared class record whose label was never synced — e.g. records merged directly by class-record:consolidate-pehm-coteaching, which attaches co-teacher pivot rows but never called the sync logic.';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $this->info($commit ? 'RUNNING IN COMMIT MODE — writes will be made.' : 'DRY RUN — no writes will be made. Pass --commit to apply.');

        $records = ClassRecord::where('status', '<>', 'archived')
            ->whereHas('coTeachers', fn ($q) => $q, '>=', 2)
            ->get();

        $changed = 0;
        $unchanged = 0;

        foreach ($records as $record) {
            $before = $record->subject_name;
            $after = $commit ? $this->applyAndReturnLabel($record) : $this->previewLabel($record);

            if ($after !== $before) {
                $this->line("cr{$record->id}: \"{$before}\" -> \"{$after}\"");
                $changed++;
            } else {
                $unchanged++;
            }
        }

        $this->newLine();
        $this->info("Totals: {$changed} label(s) ".($commit ? 'changed' : 'would change').", {$unchanged} already correct.");

        return self::SUCCESS;
    }

    private function applyAndReturnLabel(ClassRecord $record): string
    {
        $record->syncSharedDisplayLabel();

        return $record->fresh()->subject_name;
    }

    /**
     * Mirrors ClassRecord::syncSharedDisplayLabel() read-only, for dry-run
     * preview — never mutates the record.
     */
    private function previewLabel(ClassRecord $record): string
    {
        $record->loadMissing('coTeachers.subject:id,name,subject_group,grade_level');
        $primarySubject = $record->coTeachers->first()->subject;
        $group = $primarySubject?->subject_group ?? 'Shared';
        $gradeLevel = $primarySubject?->grade_level;

        if ($group === 'PEHM' && $gradeLevel !== null) {
            return 'PEHM '.($gradeLevel - 6);
        }

        $names = $record->coTeachers
            ->sortBy('subject_id')
            ->pluck('subject.name')
            ->filter()
            ->unique()
            ->values()
            ->implode(' / ');

        return "{$group} — {$names}";
    }
}
