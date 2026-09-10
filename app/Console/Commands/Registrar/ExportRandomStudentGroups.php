<?php

namespace App\Console\Commands\Registrar;

use App\Exports\RandomStudentGroupsExport;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ExportRandomStudentGroups extends Command
{
    protected $signature   = 'registrar:export-random-student-groups {--groups=6} {--output=random_student_groups.xlsx}';
    protected $description = 'Export all currently enrolled students to Excel, randomly split into N groups (stratified by grade level).';

    public function handle(): int
    {
        $groupCount = (int) $this->option('groups');

        $schoolYear = SchoolYear::where('is_current', true)->first();
        if (! $schoolYear) {
            $this->error('No current school year configured.');
            return self::FAILURE;
        }

        $this->info("School Year: {$schoolYear->name} (id={$schoolYear->id})");

        $enrollments = StudentEnrollment::where('school_year_id', $schoolYear->id)
            ->where('status', 'enrolled')
            ->get(['student_id', 'grade_level', 'section_id']);

        if ($enrollments->isEmpty()) {
            $this->error('No enrolled students found for the current school year.');
            return self::FAILURE;
        }

        $studentIds = $enrollments->pluck('student_id')->unique()->values();
        $students   = Student::whereIn('id', $studentIds)
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID', 'sex'])
            ->keyBy('id');

        $sectionIds = $enrollments->pluck('section_id')->filter()->unique()->values();
        $sections   = Section::whereIn('id', $sectionIds)
            ->get(['id', 'sectionname'])
            ->keyBy('id');

        // Build the flat roster first (grade + section label attached).
        $roster = $enrollments->map(function ($e) use ($students, $sections) {
            $student = $students->get($e->student_id);

            $sectionLabel = $e->section_id && $sections->has($e->section_id)
                ? "{$e->grade_level} - {$sections->get($e->section_id)->sectionname}"
                : "{$e->grade_level} - Unassigned";

            return [
                'full_name'     => $student?->full_name ?? 'Unknown',
                'pisays_id'     => $student?->pisaysystemID,
                'grade_section' => $sectionLabel,
                'sex'           => $student?->sex,
                'grade_level'   => $e->grade_level,
            ];
        });

        // Stratified random shuffle: shuffle within each grade level, then
        // round-robin assign each grade's shuffled students across the N
        // groups so every group gets a proportional mix of every grade.
        $groups = array_fill(1, $groupCount, []);

        foreach ($roster->groupBy('grade_level') as $gradeRows) {
            $shuffled = $gradeRows->shuffle()->values();

            foreach ($shuffled as $i => $row) {
                $groupNumber = ($i % $groupCount) + 1;
                $groups[$groupNumber][] = $row;
            }
        }

        // Flatten back into export rows, ordered by group.
        $rows = [];
        foreach ($groups as $groupNumber => $groupRows) {
            foreach ($groupRows as $row) {
                $rows[] = [
                    "Group {$groupNumber}",
                    $row['full_name'],
                    $row['pisays_id'],
                    $row['grade_section'],
                    $row['sex'],
                ];
            }
        }

        $filename = $this->option('output');
        $path     = base_path($filename);

        Excel::store(new RandomStudentGroupsExport($rows), $filename, 'local', \Maatwebsite\Excel\Excel::XLSX);

        // Maatwebsite stores relative to the given disk's root — resolve the
        // real absolute path via the filesystem manager rather than guessing,
        // since the local disk root varies by Laravel version/config.
        $storedPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filename);
        if (file_exists($storedPath) && $storedPath !== $path) {
            rename($storedPath, $path);
        }

        $this->info('Export complete: '.$path);
        $this->info("Total students: {$roster->count()}");
        foreach ($groups as $groupNumber => $groupRows) {
            $this->line("  Group {$groupNumber}: ".count($groupRows));
        }

        return self::SUCCESS;
    }
}
