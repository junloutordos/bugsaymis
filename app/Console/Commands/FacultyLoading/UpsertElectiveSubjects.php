<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use Illuminate\Console\Command;

class UpsertElectiveSubjects extends Command
{
    protected $signature = 'faculty-loading:upsert-electives
                            {--sy-id= : School year ID (defaults to current school year)}
                            {--execute : Commit changes — omit for dry-run}';

    protected $description = 'Upsert elective subjects into the subject catalogue. Dry-run by default.';

    // [grade_level, name, load_units, code]
    // Match key: (name, grade_level, school_year_id)
    private const SUBJECTS = [
        // ── AYP Electives (Grade 10) ──────────────────────────────────────────
        [10, 'Elective: Data Science (AYP)',                              3, 'EL-DS-G10'],
        [10, 'Elective: Microbiology and Basic Molecular Techniques (AYP)', 3, 'EL-MBMT-G10'],
        [10, 'Elective: Field Sampling Techniques (AYP)',                 3, 'EL-FST-G10'],
        [10, 'Elective: Design and Make: IoT (AYP)',                      3, 'EL-DMIOT-G10'],
        [10, 'Elective: Intellectual Property Rights (AYP)',              3, 'EL-IPR-G10'],
        [10, 'Elective: Philippine Biodiversity (AYP)',                   3, 'EL-PB-G10'],
        // ── Grade 11 Electives ────────────────────────────────────────────────
        [11, 'Elective: Agriculture',                                     5, 'EL-AGR-G11'],
        [11, 'Elective: Biology 3',                                       5, 'EL-BIO3-G11'],
        [11, 'Elective: Chemistry 3',                                     5, 'EL-CHEM3-G11'],
        [11, 'Elective: Chemistry 4',                                     5, 'EL-CHEM4-G11'],
        [11, 'Elective: Computer Science 5',                              5, 'EL-CS5-G11'],
        [11, 'Elective: Engineering',                                     5, 'EL-ENG-G11'],
        [11, 'Elective: Design and Make Technology',                      5, 'EL-DMT-G11'],
        [11, 'Elective: Physics 3',                                       5, 'EL-PHY3-G11'],
        // ── Grade 12 Electives ────────────────────────────────────────────────
        [12, 'Elective: Agriculture',                                     5, 'EL-AGR-G12'],
        [12, 'Elective: Biology 3',                                       5, 'EL-BIO3-G12'],
        [12, 'Elective: Biology 4',                                       5, 'EL-BIO4-G12'],
        [12, 'Elective: Chemistry 3',                                     5, 'EL-CHEM3-G12'],
        [12, 'Elective: Chemistry 4',                                     5, 'EL-CHEM4-G12'],
        [12, 'Elective: Computer Science 5',                              5, 'EL-CS5-G12'],
        [12, 'Elective: Engineering',                                     5, 'EL-ENG-G12'],
        [12, 'Elective: Design and Make Technology',                      5, 'EL-DMT-G12'],
    ];

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');

        $syId = $this->option('sy-id')
            ? (int) $this->option('sy-id')
            : SchoolYear::where('is_current', true)->value('id');

        if (! $syId) {
            $this->error('No current school year found. Pass --sy-id=<id>.');
            return self::FAILURE;
        }

        $sy = SchoolYear::find($syId);
        $this->info("School year : [{$syId}] {$sy->name}");
        $this->info('Mode        : ' . ($execute ? 'EXECUTE — changes will be committed' : 'DRY RUN — no changes'));
        $this->line('');

        $created = 0;
        $updated = 0;

        foreach (self::SUBJECTS as [$grade, $name, $units, $code]) {
            $existing = Subject::where('school_year_id', $syId)
                ->where('name', $name)
                ->where('grade_level', $grade)
                ->first();

            if ($existing) {
                $changed = (float) $existing->load_units !== (float) $units
                    || $existing->subject_type !== 'elective';

                $action = $changed ? 'UPDATE' : 'SKIP (no change)';
                $this->line(sprintf('  [%-6s] [%-14s] G%-2d  %s', $action, $code, $grade, $name));

                if ($execute && $changed) {
                    $existing->update([
                        'load_units'   => $units,
                        'credit_units' => $units,
                        'subject_type' => 'elective',
                    ]);
                    $updated++;
                }
            } else {
                $this->line(sprintf('  [CREATE] [%-14s] G%-2d  %-50s  %d units', $code, $grade, $name, $units));

                if ($execute) {
                    Subject::create([
                        'school_year_id'      => $syId,
                        'code'                => $code,
                        'name'                => $name,
                        'load_units'          => $units,
                        'credit_units'        => $units,
                        'lecture_hours'       => $units,
                        'lab_hours'           => 0,
                        'subject_type'        => 'elective',
                        'grade_level'         => $grade,
                        'semester'            => 'both',
                        'sessions_per_week'   => 1,
                        'minutes_per_session' => 60,
                        'is_active'           => true,
                    ]);
                    $created++;
                }
            }
        }

        $this->line('');

        if ($execute) {
            $this->info("Done. Created: {$created} | Updated: {$updated}");
        } else {
            $this->warn('Dry-run complete. Pass --execute to apply changes.');
        }

        return self::SUCCESS;
    }
}
