<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\Subject;
use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedSubjects extends Command
{
    protected $signature = 'faculty-loading:seed-subjects
                            {--sy-id= : School year ID (defaults to current school year)}
                            {--execute : Commit changes — omit for dry-run}';

    protected $description = 'Replace the subject catalogue for the current school year. Dry-run by default.';

    // [grade_level, name, load_units, code]
    private const SUBJECTS = [
        // ── Grade 7 ───────────────────────────────────────────────────────────
        [7,  'AdTech 1',                          3, 'AT1-G7'],
        [7,  'Computer Science 1',                3, 'CS1-G7'],
        [7,  'Earth Science',                     3, 'ES-G7'],
        [7,  'English 1',                         4, 'EN1-G7'],
        [7,  'Filipino 1',                        3, 'FIL1-G7'],
        [7,  'Health 1',                          1, 'HLT1-G7'],
        [7,  'Integrated Science 1',              5, 'ISCI1-G7'],
        [7,  'Mathematics 1',                     5, 'MATH1-G7'],
        [7,  'Music 1',                           1, 'MUS1-G7'],
        [7,  'Physical Education 1',              1, 'PE1-G7'],
        [7,  'Social Science 1',                  3, 'SS1-G7'],
        [7,  'Values Education 1',                2, 'VE1-G7'],
        // ── Grade 8 ───────────────────────────────────────────────────────────
        [8,  'AdTech 2',                          3, 'AT2-G8'],
        [8,  'Biology 1',                         3, 'BIO1-G8'],
        [8,  'Chemistry 1',                       3, 'CHEM1-G8'],
        [8,  'Computer Science 2',                3, 'CS2-G8'],
        [8,  'Earth Science',                     3, 'ES-G8'],
        [8,  'English 2',                         4, 'EN2-G8'],
        [8,  'Filipino 2',                        3, 'FIL2-G8'],
        [8,  'Health 2',                          1, 'HLT2-G8'],
        [8,  'Mathematics 2a',                    3, 'MATH2A-G8'],
        [8,  'Mathematics 2b',                    3, 'MATH2B-G8'],
        [8,  'Music 2',                           1, 'MUS2-G8'],
        [8,  'Physical Education 2',              1, 'PE2-G8'],
        [8,  'Physics 1',                         3, 'PHY1-G8'],
        [8,  'Social Science 2',                  3, 'SS2-G8'],
        [8,  'Values Education 2',                2, 'VE2-G8'],
        // ── Grade 9 ───────────────────────────────────────────────────────────
        [9,  'Biology 2',                         4, 'BIO2-G9'],
        [9,  'Chemistry 2',                       4, 'CHEM2-G9'],
        [9,  'Computer Science 3',                3, 'CS3-G9'],
        [9,  'English 3',                         3, 'EN3-G9'],
        [9,  'Filipino 3',                        3, 'FIL3-G9'],
        [9,  'Health 3',                          1, 'HLT3-G9'],
        [9,  'Mathematics 3',                     3, 'MATH3-G9'],
        [9,  'Music 3',                           1, 'MUS3-G9'],
        [9,  'Physical Education 3',              1, 'PE3-G9'],
        [9,  'Physics 2',                         4, 'PHY2-G9'],
        [9,  'Social Science 3',                  3, 'SS3-G9'],
        [9,  'Statistics 1',                      3, 'STAT1-G9'],
        [9,  'Values Education 3',                2, 'VE3-G9'],
        // ── Grade 10 ──────────────────────────────────────────────────────────
        [10, 'Biology 2',                         3, 'BIO2-G10'],
        [10, 'Chemistry 2',                       3, 'CHEM2-G10'],
        [10, 'Computer Science 4',                3, 'CS4-G10'],
        [10, 'English 4',                         3, 'EN4-G10'],
        [10, 'Filipino 4',                        3, 'FIL4-G10'],
        [10, 'Health 4',                          1, 'HLT4-G10'],
        [10, 'Mathematics 4',                     4, 'MATH4-G10'],
        [10, 'Music 4',                           1, 'MUS4-G10'],
        [10, 'Physical Education 4',              1, 'PE4-G10'],
        [10, 'Physics 2',                         3, 'PHY2-G10'],
        [10, 'Social Science 4',                  3, 'SS4-G10'],
        [10, 'STEM Research 1',                   3, 'STEMR1-G10'],
        [10, 'Values Education 4',                2, 'VE4-G10'],
        // ── Grade 11 ──────────────────────────────────────────────────────────
        [11, 'Biology 3 Level 2 (3 classes)',     5, 'BIO3L2-G11'],
        [11, 'Chemistry 3 Level 2',               5, 'CHEM3L2-G11'],
        [11, 'English 5',                         3, 'EN5-G11'],
        [11, 'Filipino 5',                        3, 'FIL5-G11'],
        [11, 'Mathematics 5 Level 1',             3, 'MATH5L1-G11'],
        [11, 'Mathematics 5 Level 2',             3, 'MATH5L2-G11'],
        [11, 'Physics 3 Level 2',                 5, 'PHY3L2-G11'],
        [11, 'Social Science 5',                  3, 'SS5-G11'],
        [11, 'STEM Research 2',                   3, 'STEMR2-G11'],
        // ── Grade 12 ──────────────────────────────────────────────────────────
        [12, 'Biology 4 Level 2 (2 classes)',     5, 'BIO4L2-G12'],
        [12, 'Chemistry 4 Level 2 (2 classes)',   5, 'CHEM4L2-G12'],
        [12, 'English 6',                         3, 'EN6-G12'],
        [12, 'Filipino 6',                        3, 'FIL6-G12'],
        [12, 'Mathematics 6 Level 1',             2, 'MATH6L1-G12'],
        [12, 'Mathematics 6 Level 2',             1, 'MATH6L2-G12'],
        [12, 'Physics 4 Level 2',                 5, 'PHY4L2-G12'],
        [12, 'Social Science 6',                  3, 'SS6-G12'],
        [12, 'STEM Research 3',                   3, 'STEMR3-G12'],
    ];

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');

        $syId = $this->option('sy-id')
            ? (int) $this->option('sy-id')
            : SchoolYear::where('is_current', true)->value('id');

        if (! $syId) {
            $this->error('No current school year found. Pass --sy-id=<id>.');
            SchoolYear::orderByDesc('id')->limit(5)->get()->each(
                fn ($sy) => $this->line("  [{$sy->id}] {$sy->name} | is_current={$sy->is_current}")
            );
            return self::FAILURE;
        }

        $sy = SchoolYear::find($syId);
        $this->info("School year : [{$syId}] {$sy->name}");
        $this->info('Mode        : ' . ($execute ? 'EXECUTE — changes will be committed' : 'DRY RUN — no changes'));
        $this->line('');

        // ── What will be deleted ──────────────────────────────────────────────
        $existing = Subject::where('school_year_id', $syId)->orderBy('grade_level')->get();
        $this->info("Subjects to DELETE ({$existing->count()}):");
        foreach ($existing as $s) {
            $this->line("  [-] [{$s->code}] G{$s->grade_level} {$s->name}");
        }

        $this->line('');

        // ── What will be created ──────────────────────────────────────────────
        $this->info('Subjects to CREATE (' . count(self::SUBJECTS) . '):');
        foreach (self::SUBJECTS as [$grade, $name, $units, $code]) {
            $this->line(sprintf('  [+] [%-12s] G%-2d  %-45s  %d units', $code, $grade, $name, $units));
        }

        if (! $execute) {
            $this->line('');
            $this->warn('Dry-run complete. Pass --execute to apply changes.');
            return self::SUCCESS;
        }

        // ── Execute ───────────────────────────────────────────────────────────
        $this->line('');
        $this->info('Deleting existing subjects...');
        $deleted = Subject::where('school_year_id', $syId)->delete();
        $this->line("  Deleted: {$deleted} rows");

        $this->line('');
        $this->info('Creating new subjects...');

        $now = now();
        $rows = array_map(fn ($s) => [
            'school_year_id'      => $syId,
            'code'                => $s[3],
            'name'                => $s[1],
            'load_units'          => $s[2],
            'credit_units'        => $s[2],
            'lecture_hours'       => $s[2],
            'lab_hours'           => 0,
            'subject_type'        => 'lecture',
            'grade_level'         => $s[0],
            'semester'            => 'both',
            'sessions_per_week'   => 1,
            'minutes_per_session' => 60,
            'is_active'           => true,
            'created_at'          => $now,
            'updated_at'          => $now,
        ], self::SUBJECTS);

        DB::table('subjects')->insert($rows);

        $this->line('  Created: ' . count($rows) . ' subjects');
        $this->line('');
        $this->info('Done.');

        return self::SUCCESS;
    }
}
