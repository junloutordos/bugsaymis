<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use Illuminate\Console\Command;

/**
 * Read-only report of catalog rows that share a display name but have
 * different ids. Neither `subjects.name` nor `sections.sectionname` has a
 * uniqueness constraint (only `subjects.code` is unique, per school year) —
 * so two catalog rows can collide on name while passing every real DB
 * constraint, since load_assignments' own uniqueness
 * (academic_term_id, subject_id, section_id) is keyed on the surrogate ids,
 * not the name. A faculty then legitimately gets one LoadAssignment row per
 * duplicate id, which looks like one assignment split into two — e.g. a
 * 3-unit subject appearing as a 2-unit row and a 1-unit row.
 *
 * This command only reports — it never merges, renames, or deletes
 * anything. Which row is "canonical" and whether historical
 * assignments/schedules/class-records need re-pointing is a judgment call
 * for CID to make per case.
 */
class AuditDuplicateCatalogNames extends Command
{
    protected $signature = 'faculty-loading:audit-duplicate-catalog
        {--school_year_id= : School year to check (default: current)}';

    protected $description = 'Report subjects/sections sharing a display name with a different id (read-only)';

    public function handle(): int
    {
        $schoolYearId = $this->option('school_year_id')
            ? (int) $this->option('school_year_id')
            : SchoolYear::where('is_current', true)->value('id');

        if (! $schoolYearId) {
            $this->error('No school year marked current and none given via --school_year_id.');
            return self::FAILURE;
        }

        $this->info("── Duplicate subject names (school_year_id={$schoolYearId}) ──");
        $this->auditSubjects($schoolYearId);

        $this->newLine();
        $this->info("── Duplicate section names (school_year_id={$schoolYearId}) ──");
        $this->auditSections($schoolYearId);

        return self::SUCCESS;
    }

    private function auditSubjects(int $schoolYearId): void
    {
        $duplicateNames = Subject::where('school_year_id', $schoolYearId)
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        if ($duplicateNames->isEmpty()) {
            $this->line('No duplicate subject names found.');
            return;
        }

        foreach ($duplicateNames as $name) {
            $subjects = Subject::where('school_year_id', $schoolYearId)->where('name', $name)->get();
            $this->warn("\"{$name}\" — {$subjects->count()} rows:");

            $rows = [];
            foreach ($subjects as $subject) {
                $assignments = LoadAssignment::where('subject_id', $subject->id)
                    ->with(['faculty:id,name', 'academicTerm:id,name', 'section:id,sectionname'])
                    ->get();

                if ($assignments->isEmpty()) {
                    $rows[] = [$subject->id, $subject->code, $subject->load_units, '—', '—', '—'];
                    continue;
                }
                foreach ($assignments as $la) {
                    $rows[] = [
                        $subject->id, $subject->code, $subject->load_units,
                        $la->faculty?->name ?? "user #{$la->user_id}",
                        $la->academicTerm?->name ?? "term #{$la->academic_term_id}",
                        $la->section?->sectionname ?? "section #{$la->section_id}",
                    ];
                }
            }
            $this->table(['Subject ID', 'Code', 'Load Units', 'Faculty', 'Term', 'Section'], $rows);
        }
    }

    private function auditSections(int $schoolYearId): void
    {
        $duplicateGroups = Section::query()
            ->where(fn ($q) => $q->where('syid', $schoolYearId)->orWhere('school_year_id', $schoolYearId))
            ->select('sectionname', 'levelid')
            ->groupBy('sectionname', 'levelid')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->line('No duplicate section names found.');
            return;
        }

        foreach ($duplicateGroups as $group) {
            $sections = Section::where(fn ($q) => $q->where('syid', $schoolYearId)->orWhere('school_year_id', $schoolYearId))
                ->where('sectionname', $group->sectionname)
                ->where('levelid', $group->levelid)
                ->get();
            $this->warn("G{$group->levelid} \"{$group->sectionname}\" — {$sections->count()} rows:");

            $rows = [];
            foreach ($sections as $section) {
                $assignments = LoadAssignment::where('section_id', $section->id)
                    ->with(['faculty:id,name', 'academicTerm:id,name', 'subject:id,name'])
                    ->get();

                if ($assignments->isEmpty()) {
                    $rows[] = [$section->id, '—', '—', '—'];
                    continue;
                }
                foreach ($assignments as $la) {
                    $rows[] = [
                        $section->id,
                        $la->faculty?->name ?? "user #{$la->user_id}",
                        $la->academicTerm?->name ?? "term #{$la->academic_term_id}",
                        $la->subject?->name ?? "subject #{$la->subject_id}",
                    ];
                }
            }
            $this->table(['Section ID', 'Faculty', 'Term', 'Subject'], $rows);
        }
    }
}
