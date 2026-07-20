<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Console\Command;

/**
 * Read-only report of faculty+subject groups whose sections currently sit on
 * different weekdays in class_schedules. When one faculty teaches the same
 * subject to multiple sections (e.g. English 7 to every Grade 7 section),
 * every section's weekly meetings are expected to land on the same
 * weekday(s) — DeterministicSchedulingService enforces this going forward
 * (assignSubjectDays() for full generates, siblingSubjectDays() for
 * incremental placement), but it does not retroactively fix schedule rows
 * that predate the rule or were hand-edited afterward. This command flags
 * those groups so CID can decide whether to regenerate or manually realign
 * them — it never moves, deletes, or otherwise changes a schedule row.
 */
class AuditScheduleDayPattern extends Command
{
    protected $signature = 'faculty-loading:audit-schedule-day-pattern
        {--academic_term_id= : Term to check (default: current term)}';

    protected $description = 'Report faculty+subject groups whose sections use inconsistent weekdays (read-only)';

    public function handle(): int
    {
        $termId = $this->option('academic_term_id')
            ? (int) $this->option('academic_term_id')
            : AcademicTerm::current()->value('id');

        if (! $termId) {
            $this->error('No academic term marked current and none given via --academic_term_id.');
            return self::FAILURE;
        }

        $term = AcademicTerm::with('schoolYear')->find($termId);
        if (! $term) {
            $this->error("No academic term found with id={$termId}.");
            return self::FAILURE;
        }

        $this->info("── Day-pattern audit — {$term->full_label} (academic_term_id={$termId}) ──");

        $rows = ClassSchedule::occupying()
            ->classes()
            ->where('academic_term_id', $termId)
            ->whereNotNull('user_id')
            ->whereNotNull('subject_id')
            ->whereNotNull('section_id')
            ->get(['user_id', 'subject_id', 'section_id', 'day_of_week']);

        if ($rows->isEmpty()) {
            $this->line('No class sessions found for this term.');
            return self::SUCCESS;
        }

        $userNames    = User::whereIn('id', $rows->pluck('user_id')->unique())->pluck('name', 'id');
        $subjectNames = Subject::whereIn('id', $rows->pluck('subject_id')->unique())->pluck('name', 'id');
        $sections     = Section::whereIn('id', $rows->pluck('section_id')->unique())->get()->keyBy('id');

        // Placeholder/TBA faculty and elective (cross-section) groups are
        // exempt from day-pattern coordination — mirrors
        // DeterministicSchedulingService's own exemptions.
        $rows = $rows->reject(function ($row) use ($userNames, $sections) {
            $isPlaceholder = str_starts_with((string) ($userNames[$row->user_id] ?? ''), 'TBA');
            $isElective    = str_starts_with((string) ($sections[$row->section_id]->sectionname ?? ''), 'ELEC-');
            return $isPlaceholder || $isElective;
        });

        $dayOrder = array_flip(SchedulingConstants::DAYS);
        $groups   = $rows->groupBy(fn ($row) => $row->user_id . ':' . $row->subject_id);

        $flagged = 0;
        foreach ($groups as $key => $groupRows) {
            [$userId, $subjectId] = array_map('intval', explode(':', (string) $key));

            $bySection = $groupRows->groupBy('section_id')->map(function ($sectionRows) use ($dayOrder) {
                $days = $sectionRows->pluck('day_of_week')->unique()->all();
                usort($days, fn ($a, $b) => ($dayOrder[$a] ?? 99) <=> ($dayOrder[$b] ?? 99));
                return $days;
            });

            if ($bySection->count() < 2) {
                continue; // only one section on this subject+faculty — nothing to compare
            }

            $signatures = $bySection->map(fn ($days) => implode(',', $days))->unique();
            if ($signatures->count() <= 1) {
                continue; // every section already agrees
            }

            $flagged++;
            $facultyName = $userNames[$userId] ?? "user #{$userId}";
            $subjectName = $subjectNames[$subjectId] ?? "subject #{$subjectId}";
            $this->warn("{$facultyName} — \"{$subjectName}\" — sections disagree on weekday:");

            $tableRows = [];
            foreach ($bySection as $sectionId => $days) {
                $tableRows[] = [
                    $sections[$sectionId]->sectionname ?? "section #{$sectionId}",
                    implode(', ', $days),
                ];
            }
            $this->table(['Section', 'Weekdays used'], $tableRows);
        }

        $this->newLine();
        if ($flagged === 0) {
            $this->line('No inconsistencies found — every multi-section faculty+subject group agrees on weekday(s).');
        } else {
            $this->warn("{$flagged} group(s) flagged.");
        }

        return self::SUCCESS;
    }
}
