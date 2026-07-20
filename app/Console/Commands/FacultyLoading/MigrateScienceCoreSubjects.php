<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Services\FacultyLoading\ScienceCoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off data migration: converts the known G11/G12 leveled-science
 * subjects (Biology/Chemistry/Physics Level 2) from regular per-homeroom
 * subjects into properly-flagged Science Core subjects with synthetic
 * cross-section classes — the same pattern ELEC-* electives already use.
 *
 * Today these subjects' load assignments point either at NULL (silently
 * skipped by the scheduling engine) or at real homerooms (Mars/Mercury/
 * Venus/...), which wrongly implies every student in that homeroom takes
 * that specific science subject. This command re-points them at new
 * synthetic "SCI-<code>-<n>" sections instead, carrying over the real
 * classroom where one was already assigned, and wipes their existing
 * (unsynchronized) class_schedules rows so the grade can be regenerated
 * cleanly by the new Science Core placement pre-pass.
 *
 * Idempotent — safe to re-run; reuses an existing synthetic section by name
 * instead of duplicating it.
 */
class MigrateScienceCoreSubjects extends Command
{
    protected $signature = 'faculty-loading:migrate-science-core
        {--school-year= : School year id (defaults to the current one)}
        {--term= : Academic term id (defaults to the load assignments\' own term)}
        {--dry-run : Preview only, no database writes}';

    protected $description = 'Convert the known G11/G12 Level-2 science subjects into flagged Science Core subjects with synthetic cross-section classes';

    /** subject code => intended number of parallel classes, confirmed with CID. */
    private const SUBJECTS = [
        'BIO3L2-G11'  => 3,
        'CHEM3L2-G11' => 1,
        'PHY3L2-G11'  => 1,
        'BIO4L2-G12'  => 2,
        'CHEM4L2-G12' => 2,
        'PHY4L2-G12'  => 1,
    ];

    public function handle(ScienceCoreService $scienceCore): int
    {
        $schoolYearId = (int) ($this->option('school-year') ?: SchoolYear::where('is_current', true)->value('id'));
        if (! $schoolYearId) {
            $this->error('No current SchoolYear found — pass --school-year explicitly.');
            return self::FAILURE;
        }

        $termId = (int) ($this->option('term') ?: AcademicTerm::where('school_year_id', $schoolYearId)->where('is_current', true)->value('id'));
        if (! $termId) {
            $this->error('No current AcademicTerm found for this school year — pass --term explicitly.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $plan   = [];

        foreach (self::SUBJECTS as $code => $expectedClasses) {
            $subject = Subject::where('school_year_id', $schoolYearId)->where('code', $code)->first();
            if (! $subject) {
                $this->warn("Subject not found, skipping: {$code}");
                continue;
            }

            $assignments = LoadAssignment::with('section')
                ->where('subject_id', $subject->id)
                ->where('assignment_type', 'teaching')
                ->where('academic_term_id', $termId)
                ->orderBy('id')
                ->get();

            if ($assignments->isEmpty()) {
                $this->warn("{$code}: no teaching load assignments found — nothing to convert.");
                continue;
            }

            if ($assignments->count() !== $expectedClasses) {
                $this->warn("{$code}: expected {$expectedClasses} class(es), found {$assignments->count()} load assignment(s). Converting what exists; add/remove the rest via the normal Load Assignments screen.");
            }

            $n = 1;
            foreach ($assignments as $la) {
                $sectionName = ScienceCoreService::SECTION_PREFIX . "{$code}-{$n}";
                $existingSection = Section::where('school_year_id', $schoolYearId)->where('sectionname', $sectionName)->first();
                $carryOverClassroomId = $existingSection?->classroom_id ?? $la->section?->classroom_id;

                $plan[] = [
                    'code'          => $code,
                    'load_assignment_id' => $la->id,
                    'faculty'       => $la->faculty?->name ?? 'TBA',
                    'from_section'  => $la->section?->sectionname ?? '(none — NULL)',
                    'to_section'    => $sectionName,
                    'section_exists'=> $existingSection !== null,
                    'classroom_id'  => $carryOverClassroomId,
                    'subject_id'    => $subject->id,
                    'grade'         => $subject->grade_level,
                    'load_assignment' => $la,
                    'target_section'   => $existingSection,
                ];
                $n++;
            }
        }

        $this->table(
            ['Subject', 'LA#', 'Faculty', 'From', 'To', 'Section exists?'],
            array_map(fn ($p) => [$p['code'], $p['load_assignment_id'], $p['faculty'], $p['from_section'], $p['to_section'], $p['section_exists'] ? 'yes (reused)' : 'no (will create)'], $plan)
        );

        if ($dryRun) {
            $this->comment('Dry run — no changes made.');
            return self::SUCCESS;
        }

        if (empty($plan)) {
            $this->comment('Nothing to migrate.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Flip these subjects to science_core, create/reuse synthetic sections, re-point load assignments, and clear their existing class_schedules rows?', false)) {
            $this->comment('Aborted — no changes made.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $schoolYearId, $termId) {
            $touchedSubjectIds = [];

            foreach ($plan as $p) {
                $section = $p['target_section'] ?? Section::create([
                    'levelid'        => $p['grade'],
                    'sectionname'    => $p['to_section'],
                    'capacity'       => 30,
                    'classroom_id'   => $p['classroom_id'],
                    'is_active'      => true,
                    'school_year_id' => $schoolYearId,
                    'syid'           => $schoolYearId,
                ]);

                /** @var LoadAssignment $la */
                $la = $p['load_assignment'];
                $la->section_id = $section->id;
                $la->save();

                $touchedSubjectIds[$p['subject_id']] = true;
            }

            Subject::whereIn('id', array_keys($touchedSubjectIds))->update(['subject_type' => 'science_core']);

            ClassSchedule::whereIn('subject_id', array_keys($touchedSubjectIds))
                ->where('academic_term_id', $termId)
                ->delete();
        });

        $this->info(count($plan) . ' load assignment(s) migrated across ' . count(array_unique(array_column($plan, 'subject_id'))) . ' subject(s). Old class_schedules rows for these subjects were cleared — regenerate Grade 11/12 next.');

        return self::SUCCESS;
    }
}
