<?php

namespace App\Console\Commands;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports a "new student intake" Google Form export (xlsx) into the legacy
 * students table + creates matching student_enrollments rows.
 *
 * Column layout is hardcoded by letter (not header text) because the form's
 * headers contain apostrophes/parentheses that are brittle to string-match;
 * a sanity check on row 1 catches a mismatched file early instead.
 */
class ImportStudentIntake extends Command
{
    protected $signature = 'students:import-intake
        {source : Local file path, or an S3 key when --disk=s3}
        {--disk=local : local|s3}
        {--dry-run : Parse and preview only — no database writes}
        {--start-seq=1 : Starting sequence number for pisaysystemID (13-YYYY-XXX)}
        {--campus=13 : Campus number prefix for pisaysystemID}
        {--year=2026 : Entry calendar year — drives pisaysystemID and batch (graduation year)}
        {--encoded-by= : Name stored in students.encodedby}';

    protected $description = 'Import a new-student intake form export (xlsx) into students + student_enrollments';

    // Column letters, per the "INFO GRADE 7.xlsx" Google Form export layout.
    private const COL_TIMESTAMP      = 'A';
    private const COL_LASTNAME       = 'C';
    private const COL_FIRSTNAME      = 'D';
    private const COL_MIDDLENAME     = 'E';
    private const COL_NAME_EXT       = 'F';
    private const COL_NICKNAME       = 'G';
    private const COL_BIRTHDAY       = 'H';
    private const COL_SEX            = 'I';
    private const COL_STUDENT_CELL   = 'J';
    private const COL_RELIGION       = 'K';
    private const COL_BLOODTYPE      = 'L';
    private const COL_NATIONALITY    = 'M';
    private const COL_STUDENT_CITZ   = 'N';
    private const COL_ENTRY_TYPE     = 'O';
    private const COL_SCHOLAR_TYPE   = 'P';
    private const COL_DORMER         = 'Q';
    private const COL_ELEM_SCHOOL    = 'R';
    private const COL_SCHOOL_TYPE    = 'S';
    private const COL_LRN            = 'T';
    private const COL_GRAD_DATE      = 'U';
    private const COL_GRAD_REGION    = 'V';
    private const COL_NUM_GRADUATES  = 'W';
    private const COL_AVERAGE        = 'X';
    private const COL_HONORS         = 'Y';
    private const COL_REGION         = 'Z';
    private const COL_PROVINCE       = 'AA';
    private const COL_CITY           = 'AB';
    private const COL_HOME_ADDRESS   = 'AC';
    private const COL_HOME_OWNERSHIP = 'AD';
    private const COL_CONTACT_PERSON = 'AE';
    private const COL_CONTACT_NO1    = 'AF';
    private const COL_RELATION1      = 'AG';
    private const COL_MOTHER_NAME    = 'AH';
    private const COL_MOTHER_ADDR    = 'AI';
    private const COL_MOTHER_CITZ    = 'AJ';
    private const COL_MOTHER_CP      = 'AK';
    private const COL_MOTHER_EMAIL   = 'AL';
    private const COL_MOTHER_OCC     = 'AM';
    private const COL_MOTHER_COMPANY = 'AN';
    private const COL_MOTHER_OFC_TEL = 'AO';
    private const COL_MOTHER_OFC_ADDR = 'AP';
    private const COL_MOTHER_ALUMNUS = 'AQ';
    private const COL_FATHER_NAME    = 'AR';
    private const COL_FATHER_ADDR    = 'AS';
    private const COL_FATHER_CITZ    = 'AT';
    private const COL_FATHER_CP      = 'AU';
    private const COL_FATHER_EMAIL   = 'AV';
    private const COL_FATHER_OCC     = 'AW';
    private const COL_FATHER_COMPANY = 'AX';
    private const COL_FATHER_OFC_TEL = 'AY';
    private const COL_FATHER_OFC_ADDR = 'AZ';
    private const COL_FATHER_ALUMNUS = 'BA';

    /**
     * Production's students table was created out-of-band before its migration existed
     * (see the migration's guard comment) and drifted from it: these columns are
     * NOT NULL with no default in prod, even though the migration (and dev's copy, built
     * from that migration) declares DEFAULT ''. None of the form data maps to them, so
     * without this they fail the insert outright. Confirmed against prod's live
     * `SHOW COLUMNS` (2026-07-20) — not guessed from the migration text.
     */
    private const NOT_NULL_NO_DEFAULT_FILLERS = [
        'ethnic' => '',
        'mbirthday' => '',
        'fbirthday' => '',
        'meduc' => '',
        'feduc' => '',
        'mschool' => '',
        'fschool' => '',
        'parentsstatus' => '',
        'zipcode' => '',
        'schooladdress' => '',
        'contact_address1' => '',
        'contact_ofc_address1' => '',
        'contact_ofc_telno1' => '',
        'contactperson2' => '',
        'relation2' => '',
        'contact_address2' => '',
        'contactno2' => '',
        'contact_ofc_address2' => '',
        'contact_ofc_telno2' => '',
        'socioeconomic' => '',
        'schoolType1' => '',
        'schoolType2' => '',
    ];

    public function handle(): int
    {
        $sourcePath = $this->resolveSourcePath();
        if (! $sourcePath) {
            return self::FAILURE;
        }

        $sheet = IOFactory::load($sourcePath)->getActiveSheet();

        if (! $this->sanityCheckHeaders($sheet)) {
            return self::FAILURE;
        }

        $rows   = $this->readRows($sheet);
        $unique = $this->dedupe($rows);

        usort($unique, fn ($a, $b) => [$a['_sortLast'], $a['_sortFirst']] <=> [$b['_sortLast'], $b['_sortFirst']]);

        $existingLrns = Student::whereIn('lrn', array_values(array_filter(array_column($unique, 'lrn'))))
            ->pluck('lrn')
            ->all();

        $campus = (int) $this->option('campus');
        $year   = (int) $this->option('year');
        $seq    = (int) $this->option('start-seq');
        $encodedBy = $this->option('encoded-by') ?: 'Batch Import';

        $preview  = [];
        $toInsert = [];

        foreach ($unique as $u) {
            $flags = $u['_flags'];
            $skip  = false;

            if ($u['lrn'] && in_array($u['lrn'], $existingLrns, true)) {
                $flags[] = 'lrn_already_in_db';
                $skip = true;
            }

            $gradeLevel = $u['grade_level'];
            $batch      = $year + 13 - $gradeLevel;
            $pisaysId   = $skip ? null : sprintf('%d-%d-%03d', $campus, $year, $seq);

            $record = array_merge(self::NOT_NULL_NO_DEFAULT_FILLERS, $u['mapped'], [
                'lastname'      => $u['lastname'],
                'firstname'     => $u['firstname'],
                'middlename'    => $u['middlename'],
                'lrn'           => $u['lrn'],
                'batch'         => (string) $batch,
                'status'        => 'Enrolled',
                'encodedby'     => $encodedBy,
                'date_encoded'  => now()->format('Y-m-d'),
                'pisaysystemID' => $pisaysId,
                'pkey'          => $pisaysId,
            ]);

            [$record, $encodingFlags] = $this->applyLatin1Guard($record);
            [$record, $truncationFlags] = $this->applyLengthGuard($record);
            $flags = array_merge($flags, $encodingFlags, $truncationFlags);

            $remarkParts = array_values(array_filter($flags, fn ($f) =>
                str_starts_with($f, 'lrn_missing')
                || str_starts_with($f, 'lrn_conflict')
                || str_starts_with($f, 'lrn_suspicious_length')
                || str_starts_with($f, 'given_name_varied')
                || str_starts_with($f, 'truncated:')
                || str_starts_with($f, 'transliterated:')
                || $f === 'birthday_year_corrected'
            ));
            $record['remarks'] = $remarkParts ? ('Import ' . now()->format('Y-m-d') . ': ' . implode('; ', $remarkParts)) : null;

            $preview[] = [
                'id'    => $pisaysId ?? '—',
                'name'  => "{$u['lastname']}, {$u['firstname']}",
                'grade' => $gradeLevel,
                'batch' => $batch,
                'lrn'   => $u['lrn'] ?? '—',
                'flags' => $flags ? implode(', ', $flags) : '—',
            ];

            if (! $skip) {
                $seq++;
                $toInsert[] = ['record' => $record, 'grade_level' => $gradeLevel];
            }
        }

        $this->table(['PisaysID', 'Name', 'Grade', 'Batch', 'LRN', 'Flags'], $preview);
        $this->info(sprintf(
            '%d unique students parsed (from %d raw rows). %d to insert, %d skipped (LRN already in students table).',
            count($unique), count($rows), count($toInsert), count($unique) - count($toInsert)
        ));

        if ($this->option('dry-run')) {
            $this->comment('Dry run — no changes made.');
            return self::SUCCESS;
        }

        if (empty($toInsert)) {
            $this->comment('Nothing to insert.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Proceed with inserting ' . count($toInsert) . ' students and creating their enrollments?', false)) {
            $this->comment('Aborted — no changes made.');
            return self::SUCCESS;
        }

        $currentSY = SchoolYear::where('is_current', true)->first();
        if (! $currentSY) {
            $this->error('No current SchoolYear found (school_years.is_current) — aborting, cannot create enrollments.');
            return self::FAILURE;
        }

        $encodedByUser = User::where('name', $encodedBy)->first();

        DB::transaction(function () use ($toInsert, $currentSY, $encodedByUser) {
            foreach ($toInsert as $row) {
                $studentId = DB::table('students')->insertGetId($row['record']);

                StudentEnrollment::create([
                    'student_id'      => $studentId,
                    'school_year_id'  => $currentSY->id,
                    'section_id'      => null,
                    'grade_level'     => $row['grade_level'],
                    'enrollment_type' => 'new',
                    'status'          => 'enrolled',
                    'enrollment_date' => now()->format('Y-m-d'),
                    'encoded_by'      => $encodedByUser?->id,
                ]);
            }
        });

        $this->info(count($toInsert) . " students imported and enrolled for SY {$currentSY->name}.");

        return self::SUCCESS;
    }

    private function resolveSourcePath(): ?string
    {
        $source = $this->argument('source');

        if ($this->option('disk') === 's3') {
            if (! Storage::disk('s3')->exists($source)) {
                $this->error("S3 object not found: {$source}");
                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'intake_') . '.xlsx';
            file_put_contents($tmp, Storage::disk('s3')->get($source));
            return $tmp;
        }

        if (! file_exists($source)) {
            $this->error("File not found: {$source}");
            return null;
        }

        return $source;
    }

    private function sanityCheckHeaders(Worksheet $sheet): bool
    {
        $checks = [
            self::COL_LASTNAME  => 'LAST NAME',
            self::COL_FIRSTNAME => 'GIVEN NAME',
            self::COL_ENTRY_TYPE => 'TYPE OF ENTRY',
            self::COL_LRN        => 'REFERENCE NUMBER',
            self::COL_AVERAGE    => 'AVERAGE',
        ];

        foreach ($checks as $col => $expectedSubstring) {
            $header = trim((string) $sheet->getCell("{$col}1")->getValue());
            if (! str_contains(mb_strtoupper($header), $expectedSubstring)) {
                $this->error("Unexpected header at column {$col}1: \"{$header}\" (expected something containing \"{$expectedSubstring}\"). Aborting — file layout may have changed.");
                return false;
            }
        }

        return true;
    }

    private function readRows(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $rows = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $val = fn (string $col) => $sheet->getCell("{$col}{$r}")->getValue();

            $last  = trim((string) $val(self::COL_LASTNAME));
            $first = trim((string) $val(self::COL_FIRSTNAME));
            if ($last === '' && $first === '') {
                continue;
            }

            $entryType  = trim((string) $val(self::COL_ENTRY_TYPE));
            $gradeLevel = 7;
            if (preg_match('/Grade\s*(\d+)/i', $entryType, $m)) {
                $gradeLevel = (int) $m[1];
            }

            $suffix   = trim((string) $val(self::COL_NAME_EXT));
            if ($suffix !== '' && preg_match('/^(n\/?a\.?|none|no|-+|n\.a\.?)$/i', $suffix)) {
                $suffix = '';
            }
            $lastname = $suffix !== '' ? "{$last} {$suffix}" : $last;

            [$lrn, $lrnFlag] = $this->cleanLrn($val(self::COL_LRN));
            if ($lrn !== null && strlen($lrn) !== 12) {
                $lrnFlag = ($lrnFlag ? $lrnFlag . '+' : '') . 'lrn_suspicious_length(' . strlen($lrn) . 'd)';
            }

            $dormerRaw = trim((string) $val(self::COL_DORMER));

            [$birthday, $birthdayCorrected] = $this->excelDateFromCellWithFlag($sheet->getCell(self::COL_BIRTHDAY . $r));

            $mapped = [
                'nickname'           => $this->str($val(self::COL_NICKNAME)),
                'sex'                => $this->str($val(self::COL_SEX)),
                'studentcontact'     => $this->str($val(self::COL_STUDENT_CELL)),
                'religion'           => $this->str($val(self::COL_RELIGION)),
                'bloodtype'          => $this->str($val(self::COL_BLOODTYPE)),
                'nationality'        => $this->str($val(self::COL_NATIONALITY)),
                'studentcitizenship' => $this->str($val(self::COL_STUDENT_CITZ)),
                'entrytype'          => $this->str($entryType),
                // schocat is varchar(15) — "Regional Scholar"/"City Scholar" don't fit; store the short code.
                'schocat'            => $this->shortScholarCode($val(self::COL_SCHOLAR_TYPE)),
                'dormer'             => $this->str($dormerRaw),
                'dormer1'            => str_contains($dormerRaw, 'Intern') ? 'Dormer' : (str_contains($dormerRaw, 'Extern') ? 'Non-dormer' : null),
                'elemschool'         => $this->str($val(self::COL_ELEM_SCHOOL)),
                'schooltype'         => $this->str($val(self::COL_SCHOOL_TYPE)),
                'regiongraduated'    => $this->str($val(self::COL_GRAD_REGION)),
                'noofgraduates'      => is_numeric($val(self::COL_NUM_GRADUATES)) ? (int) $val(self::COL_NUM_GRADUATES) : null,
                'honors'             => $this->str($val(self::COL_HONORS)),
                'region'             => $this->str($val(self::COL_REGION)),
                'province'           => $this->str($val(self::COL_PROVINCE)),
                'municipal'          => $this->str($val(self::COL_CITY)),
                'houseno'            => $this->str($val(self::COL_HOME_ADDRESS)),
                'homeaddresstype'    => $this->str($val(self::COL_HOME_OWNERSHIP)),
                'contactperson'      => $this->str($val(self::COL_CONTACT_PERSON)),
                'contactno1'         => $this->str($val(self::COL_CONTACT_NO1)),
                'relation1'          => $this->str($val(self::COL_RELATION1)),
                'mother_name'        => $this->str($val(self::COL_MOTHER_NAME)),
                'motheraddress'      => $this->str($val(self::COL_MOTHER_ADDR)),
                'mcitizenship'       => $this->str($val(self::COL_MOTHER_CITZ)),
                'mcpno'              => $this->str($val(self::COL_MOTHER_CP)),
                'memailaddress'      => $this->str($val(self::COL_MOTHER_EMAIL)),
                'moccupation'        => $this->str($val(self::COL_MOTHER_OCC)),
                'mcompany'           => $this->str($val(self::COL_MOTHER_COMPANY)),
                'mofctelno'          => $this->str($val(self::COL_MOTHER_OFC_TEL)),
                'mofcaddress'        => $this->str($val(self::COL_MOTHER_OFC_ADDR)),
                'malumnus'           => $this->str($val(self::COL_MOTHER_ALUMNUS)),
                'father_name'        => $this->str($val(self::COL_FATHER_NAME)),
                'fatheraddress'      => $this->str($val(self::COL_FATHER_ADDR)),
                'fcitizenship'       => $this->str($val(self::COL_FATHER_CITZ)),
                'fcpno'              => $this->str($val(self::COL_FATHER_CP)),
                'femailaddress'      => $this->str($val(self::COL_FATHER_EMAIL)),
                'foccupation'        => $this->str($val(self::COL_FATHER_OCC)),
                'fcompany'           => $this->str($val(self::COL_FATHER_COMPANY)),
                'fofctelno'          => $this->str($val(self::COL_FATHER_OFC_TEL)),
                'fofcaddress'        => $this->str($val(self::COL_FATHER_OFC_ADDR)),
                'falumnus'           => $this->str($val(self::COL_FATHER_ALUMNUS)),
                'birthday'           => $birthday,
                'dateofgraduation'   => $this->excelDateFromCell($sheet->getCell(self::COL_GRAD_DATE . $r)),
                'average'            => $this->averageFromCell($sheet->getCell(self::COL_AVERAGE . $r)),
                'middlename'         => $this->str($val(self::COL_MIDDLENAME)),
            ];

            $flags = [];
            if ($lrnFlag) {
                $flags[] = $lrnFlag;
            }
            if ($birthdayCorrected) {
                $flags[] = 'birthday_year_corrected';
            }

            $rows[] = [
                'lastname'    => $lastname,
                'firstname'   => $first,
                'middlename'  => $mapped['middlename'],
                '_sortLast'   => mb_strtolower($last),
                '_sortFirst'  => mb_strtolower($first),
                '_dedupeKey'  => mb_strtolower($last) . '|' . ($mapped['birthday'] ?? ('noname:' . mb_strtolower($first))),
                'lrn'         => $lrn,
                'timestamp'   => $this->excelDateFromCell($sheet->getCell(self::COL_TIMESTAMP . $r), true),
                'grade_level' => $gradeLevel,
                'mapped'      => $mapped,
                '_flags'      => $flags,
            ];
        }

        return $rows;
    }

    private function dedupe(array $rows): array
    {
        // Grouped by (lastname, birthday) rather than (lastname, firstname) — parents
        // resubmitting the form sometimes vary the given name (e.g. "Allison" vs
        // "Allison Faith") but birthday + lastname reliably identifies the same child.
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['_dedupeKey']][] = $row;
        }

        $unique = [];
        foreach ($groups as $group) {
            usort($group, function ($a, $b) {
                $ta = $a['timestamp'] ? Carbon::parse($a['timestamp'])->timestamp : 0;
                $tb = $b['timestamp'] ? Carbon::parse($b['timestamp'])->timestamp : 0;
                return $ta <=> $tb;
            });

            $latest = end($group);

            if (count($group) > 1) {
                $lrns = array_values(array_unique(array_filter(array_column($group, 'lrn'))));
                if (count($lrns) > 1) {
                    $latest['_flags'][] = 'lrn_conflict_between_submissions:' . implode('/', $lrns);
                }

                $firstnames = array_values(array_unique(array_map('mb_strtolower', array_column($group, 'firstname'))));
                if (count($firstnames) > 1) {
                    $latest['_flags'][] = 'given_name_varied_between_submissions:' . implode('/', array_column($group, 'firstname'));
                }

                $latest['_flags'][] = 'duplicate_submission_kept_latest';
            }

            $unique[] = $latest;
        }

        return $unique;
    }

    private function cleanLrn($raw): array
    {
        if ($raw === null) {
            return [null, 'lrn_missing'];
        }

        $s = (is_float($raw) || is_int($raw)) ? sprintf('%.0f', $raw) : trim((string) $raw);
        if ($s === '') {
            return [null, 'lrn_missing'];
        }

        $digits = preg_replace('/[^0-9]/', '', $s);
        if ($digits === '') {
            return [null, 'lrn_missing'];
        }

        return [$digits, null];
    }

    private function excelDateFromCell(?Cell $cell, bool $withTime = false): ?string
    {
        [$date, ] = $this->excelDateFromCellWithFlag($cell, $withTime);
        return $date;
    }

    /**
     * Returns [dateString, wasTwoDigitYearCorrected]. Some parents typed the birthday as
     * literal text with a 2-digit year (e.g. "12/31/13"); Carbon parses that as AD 13
     * rather than 2013. That's never a legitimate value here, so it's corrected to 20YY
     * and flagged (birthdate is a legally significant field — corrected, not silently guessed away).
     */
    private function excelDateFromCellWithFlag(?Cell $cell, bool $withTime = false): array
    {
        if (! $cell) {
            return [null, false];
        }

        $v = $cell->getValue();
        if ($v === null || $v === '') {
            return [null, false];
        }

        if (is_numeric($v) && ExcelDate::isDateTime($cell)) {
            $dt = ExcelDate::excelToDateTimeObject($v);
            $c  = Carbon::instance($dt);
            return [$withTime ? $c->toDateTimeString() : $c->format('Y-m-d'), false];
        }

        try {
            $c = Carbon::parse((string) $v);
            $corrected = false;
            if ($c->year < 100) {
                $c = $c->addYears(2000);
                $corrected = true;
            }
            return [$withTime ? $c->toDateTimeString() : $c->format('Y-m-d'), $corrected];
        } catch (\Throwable $e) {
            return [null, false];
        }
    }

    private function averageFromCell(?Cell $cell): ?float
    {
        if (! $cell) {
            return null;
        }

        $raw = $cell->getValue();
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $avg = (float) $raw;
            $fmt = $cell->getStyle()->getNumberFormat()->getFormatCode();
            if (str_contains($fmt, '%')) {
                $avg *= 100;
            }
            return round($avg, 2);
        }

        $digits = preg_replace('/[^0-9.]/', '', (string) $raw);
        return $digits !== '' ? round((float) $digits, 2) : null;
    }

    private function str($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function shortScholarCode($raw): ?string
    {
        $s = $this->str($raw);
        if ($s === null) {
            return null;
        }
        if (stripos($s, 'Regional') !== false) {
            return 'Regional';
        }
        if (stripos($s, 'City') !== false) {
            return 'City';
        }
        return mb_substr($s, 0, 15);
    }

    /**
     * Fetch live varchar/char column limits so we never hit "Data too long" mid-batch —
     * this legacy table has several columns (schocat, contactno1, ...) far narrower than
     * the free text the Google Form actually collects.
     */
    private function columnLimits(): array
    {
        static $limits = null;
        if ($limits !== null) {
            return $limits;
        }

        $limits = [];
        foreach (DB::select('SHOW COLUMNS FROM students') as $col) {
            if (preg_match('/^(?:var)?char\((\d+)\)/i', $col->Type, $m)) {
                $limits[$col->Field] = (int) $m[1];
            }
        }

        return $limits;
    }

    /**
     * The students table is DEFAULT CHARSET=latin1 — a foreign address/name with
     * non-Latin1 characters (e.g. Czech "Třemošnice") fails the insert outright.
     * Transliterate to the nearest ASCII rather than lose the whole row.
     */
    private function applyLatin1Guard(array $record): array
    {
        $flags = [];

        foreach ($record as $col => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            // Target ASCII (not ISO-8859-1) so the result stays valid UTF-8 bytes on the wire —
            // converting to Latin-1 bytes here would get re-mangled by the utf8mb4 connection charset.
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            $converted = $converted !== false ? trim($converted) : preg_replace('/[^\x00-\x7F]/', '', $value);

            if ($converted !== $value) {
                $record[$col] = $converted;
                $flags[] = "transliterated:{$col}";
            }
        }

        return [$record, $flags];
    }

    /**
     * Truncate any field that would overflow its column, preferring to keep just the
     * first "/"-separated value (e.g. two phone numbers jammed into one field) over a
     * mid-string cut. Returns [record, flags].
     */
    private function applyLengthGuard(array $record): array
    {
        $limits = $this->columnLimits();
        $flags  = [];

        foreach ($record as $col => $value) {
            if (! is_string($value) || ! isset($limits[$col])) {
                continue;
            }

            $limit = $limits[$col];
            if (mb_strlen($value) <= $limit) {
                continue;
            }

            $fitted = $value;
            if (str_contains($fitted, '/')) {
                $first = trim(explode('/', $fitted)[0]);
                if (mb_strlen($first) <= $limit) {
                    $fitted = $first;
                }
            }

            if (mb_strlen($fitted) > $limit) {
                $fitted = mb_substr($fitted, 0, $limit);
            }

            $record[$col] = $fitted;
            $flags[] = "truncated:{$col}";
        }

        return [$record, $flags];
    }
}
