<?php

namespace App\Console\Commands;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

/**
 * Matches a Google Workspace admin export (one CSV per grade: grade7.csv..grade12.csv)
 * against production students enrolled at that grade level for the current school year,
 * and writes the official @crc.pshs.edu.ph address into students.student_email.
 *
 * Matching is intentionally conservative: scoped to the exact grade level (current SY),
 * lastname must match exactly (normalized), and the student's firstname tokens must be a
 * subset of the CSV's combined first-name field (or vice versa). Zero or multiple
 * candidates => skipped and flagged, never guessed.
 */
class SyncOfficialStudentEmails extends Command
{
    protected $signature = 'students:sync-official-emails
        {source : Local directory, or an S3 prefix (folder) when --disk=s3, containing gradeN.csv files}
        {--disk=local : local|s3}
        {--grade= : Only process this grade level (e.g. 7) instead of all files found}
        {--dry-run : Parse and preview only — no database writes}';

    protected $description = 'Match a Google Workspace official-email export (per grade CSV) against production students and update students.student_email';

    public function handle(): int
    {
        $files = $this->resolveGradeFiles();
        if ($files === null) {
            return self::FAILURE;
        }

        if (empty($files)) {
            $this->error('No gradeN.csv files found (expected filenames like grade7.csv, grade10.csv, ...).');
            return self::FAILURE;
        }

        $currentSY = SchoolYear::where('is_current', true)->first();
        if (! $currentSY) {
            $this->error('No current SchoolYear found (school_years.is_current) — aborting.');
            return self::FAILURE;
        }

        $totalUpdated = 0;
        $totalUnchanged = 0;
        $totalSkipped = 0;

        foreach ($files as $grade => $path) {
            $this->info("── Grade {$grade} ({$currentSY->name}) " . str_repeat('─', 40));

            $rows = $this->readCsv($path);
            $candidates = $this->loadCandidates($currentSY->id, $grade);

            [$plan, $stats] = $this->buildPlan($rows, $candidates);

            $this->table(['Name (CSV)', 'Official Email', 'Match', 'Action'], array_map(fn ($p) => [
                $p['csv_name'],
                $p['email'],
                $p['match_label'],
                $p['action'],
            ], $plan));

            $this->info(sprintf(
                'Grade %s: %d rows — %d to update, %d already correct, %d skipped.',
                $grade, count($rows), $stats['update'], $stats['unchanged'], $stats['skipped']
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            if ($stats['update'] === 0) {
                $this->comment('Nothing to update for this grade.');
                continue;
            }

            if (! $this->confirm("Apply {$stats['update']} email update(s) for Grade {$grade}?", false)) {
                $this->comment('Skipped — no changes made for this grade.');
                continue;
            }

            foreach ($plan as $p) {
                if ($p['action'] !== 'update') {
                    continue;
                }

                DB::table('students')->where('id', $p['student_id'])->update([
                    'student_email' => $p['email'],
                    'remarks'       => $p['new_remarks'],
                ]);
            }

            $totalUpdated += $stats['update'];
            $totalUnchanged += $stats['unchanged'];
            $totalSkipped += $stats['skipped'];
        }

        if (! $this->option('dry-run')) {
            $this->info("Done. {$totalUpdated} updated, {$totalUnchanged} already correct, {$totalSkipped} skipped across all processed grades.");
        } else {
            $this->comment('Dry run — no changes made.');
        }

        return self::SUCCESS;
    }

    /** @return array<int,string>|null Map of grade level => local file path */
    private function resolveGradeFiles(): ?array
    {
        $source = $this->argument('source');
        $onlyGrade = $this->option('grade') ? (int) $this->option('grade') : null;

        $entries = [];

        if ($this->option('disk') === 's3') {
            $prefix = rtrim($source, '/') . '/';
            $objects = Storage::disk('s3')->files($prefix);
            foreach ($objects as $key) {
                if (preg_match('/grade(\d+)\.csv$/i', basename($key), $m)) {
                    $grade = (int) $m[1];
                    if ($onlyGrade && $grade !== $onlyGrade) {
                        continue;
                    }
                    $tmp = tempnam(sys_get_temp_dir(), 'emails_') . '.csv';
                    file_put_contents($tmp, Storage::disk('s3')->get($key));
                    $entries[$grade] = $tmp;
                }
            }
        } else {
            if (! is_dir($source)) {
                $this->error("Directory not found: {$source}");
                return null;
            }
            foreach (glob(rtrim($source, '/') . '/grade*.csv') as $path) {
                if (preg_match('/grade(\d+)\.csv$/i', basename($path), $m)) {
                    $grade = (int) $m[1];
                    if ($onlyGrade && $grade !== $onlyGrade) {
                        continue;
                    }
                    $entries[$grade] = $path;
                }
            }
        }

        ksort($entries);
        return $entries;
    }

    private function readCsv(string $path): array
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $rows = [];
        foreach ($csv->getRecords() as $record) {
            $last  = trim((string) ($record['Last Name [Required]'] ?? ''));
            $first = trim((string) ($record['First Name [Required]'] ?? ''));
            $email = trim((string) ($record['Email Address [Required]'] ?? ''));
            if ($last === '' && $first === '') {
                continue;
            }

            $rows[] = [
                'last'  => $last,
                'first' => $first,
                'email' => $email,
                'status' => trim((string) ($record['Status [READ ONLY]'] ?? '')),
                'corrupted' => str_contains($last, "\u{FFFD}") || str_contains($first, "\u{FFFD}"),
            ];
        }

        return $rows;
    }

    private function loadCandidates(int $schoolYearId, int $grade)
    {
        $studentIds = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->where('grade_level', $grade)
            ->pluck('student_id');

        return Student::whereIn('id', $studentIds)
            ->get(['id', 'lastname', 'firstname', 'middlename', 'student_email', 'remarks']);
    }

    private function buildPlan(array $rows, $candidates): array
    {
        $stats = ['update' => 0, 'unchanged' => 0, 'skipped' => 0];
        $plan = [];

        // Pre-index candidates by normalized lastname to avoid an O(n*m) full scan per row.
        $byLastname = [];
        foreach ($candidates as $c) {
            $byLastname[$this->normalize($c->lastname)][] = $c;
        }

        foreach ($rows as $row) {
            $csvName = trim("{$row['last']}, {$row['first']}");

            if ($row['corrupted']) {
                $plan[] = ['csv_name' => $csvName, 'email' => $row['email'], 'match_label' => '—', 'action' => 'skip:corrupted_name'];
                $stats['skipped']++;
                continue;
            }

            $lastKey = $this->normalize($row['last']);
            $csvTokens = $this->tokens($row['first']);

            $matches = array_values(array_filter($byLastname[$lastKey] ?? [], function ($c) use ($csvTokens) {
                $studentTokens = $this->tokens(trim($c->firstname . ' '));
                if (empty($studentTokens)) {
                    return false;
                }
                $subsetOfCsv = empty(array_diff($studentTokens, $csvTokens));
                $csvSubsetOfStudent = empty(array_diff($csvTokens, $this->tokens(trim($c->firstname . ' ' . $c->middlename))));
                return $subsetOfCsv || $csvSubsetOfStudent;
            }));

            if (count($matches) === 0) {
                $plan[] = ['csv_name' => $csvName, 'email' => $row['email'], 'match_label' => 'no match', 'action' => 'skip:no_match'];
                $stats['skipped']++;
                continue;
            }

            if (count($matches) > 1) {
                $plan[] = ['csv_name' => $csvName, 'email' => $row['email'], 'match_label' => count($matches) . ' candidates', 'action' => 'skip:ambiguous'];
                $stats['skipped']++;
                continue;
            }

            $student = $matches[0];
            $matchLabel = "{$student->lastname}, {$student->firstname}";

            if ($student->student_email === $row['email']) {
                $plan[] = ['csv_name' => $csvName, 'email' => $row['email'], 'match_label' => $matchLabel, 'action' => 'unchanged'];
                $stats['unchanged']++;
                continue;
            }

            $note = $student->student_email
                ? "Import " . now()->format('Y-m-d') . ": student_email updated from official roster (was: {$student->student_email})"
                : "Import " . now()->format('Y-m-d') . ": student_email set from official roster";

            $newRemarks = $student->remarks ? "{$student->remarks} | {$note}" : $note;
            if (mb_strlen($newRemarks) > 500) {
                $newRemarks = mb_substr($newRemarks, -500);
            }

            $plan[] = [
                'csv_name'    => $csvName,
                'email'       => $row['email'],
                'match_label' => $matchLabel . ($student->student_email ? " (was: {$student->student_email})" : ' (was: blank)'),
                'action'      => 'update',
                'student_id'  => $student->id,
                'new_remarks' => $newRemarks,
            ];
            $stats['update']++;
        }

        return [$plan, $stats];
    }

    private function normalize(?string $v): string
    {
        $v = trim((string) $v);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        $ascii = $ascii !== false ? $ascii : preg_replace('/[^\x00-\x7F]/', '', $v);
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $ascii)));
    }

    private function tokens(?string $v): array
    {
        $normalized = $this->normalize($v);
        return $normalized === '' ? [] : explode(' ', $normalized);
    }
}
