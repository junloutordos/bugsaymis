<?php

namespace App\Console\Commands\Registrar;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSchoolYearId extends Command
{
    protected $signature   = 'registrar:fix-school-year-id';
    protected $description = 'Rename school_years id=4 to id=13 to continue legacy syid sequence, cascade to all FK tables.';

    // All tables with FK constraints on school_years.id
    private const FK_TABLES = [
        'academic_terms',
        'class_records',
        'class_schedules',
        'enrollment_periods',
        'faculty_committee_assignments',
        'faculty_loads',
        'faculty_vacancies',
        'load_assignments',
        'overload_computations',
        'research_advisories',
        'retention_policies',
        'section_fixed_slots',
        'sections',
        'student_academic_standing',
        'student_annual_grades',
        'student_enrollments',
        'student_form_submissions',
        'training_records',
    ];

    public function handle(): int
    {
        // Idempotent guard
        if (! DB::table('school_years')->where('id', 4)->exists()) {
            if (DB::table('school_years')->where('id', 13)->exists()) {
                $this->info('Already done — school_years id=13 exists, id=4 gone. Nothing to do.');
            } else {
                $this->warn('school_years id=4 not found and id=13 also missing. Check the table manually.');
            }
            return self::SUCCESS;
        }

        $this->info('Starting school_years id: 4 → 13');

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            // 1. Rename the PK
            DB::table('school_years')->where('id', 4)->update(['id' => 13]);
            $this->line('  ✓ school_years id updated to 13');

            // 2. Cascade to all FK tables
            foreach (self::FK_TABLES as $table) {
                $affected = DB::table($table)
                    ->where('school_year_id', 4)
                    ->update(['school_year_id' => 13]);

                if ($affected > 0) {
                    $this->line("  ✓ {$table}: {$affected} row(s) updated");
                }
            }

            // 3. Also update sections.syid (legacy old-system field mirrors school_year_id)
            $syidAffected = DB::table('sections')
                ->where('syid', 4)
                ->where('school_year_id', 13) // already updated above
                ->update(['syid' => 13]);

            if ($syidAffected > 0) {
                $this->line("  ✓ sections.syid: {$syidAffected} row(s) updated to 13");
            }

            // 4. Reset AUTO_INCREMENT
            DB::statement('ALTER TABLE school_years AUTO_INCREMENT = 14');
            $this->line('  ✓ school_years AUTO_INCREMENT set to 14');

        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->info('Done. SY 2026-2027 is now id=13.');

        return self::SUCCESS;
    }
}
