<?php

namespace Database\Seeders;

use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\SstPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds sst_position_id, specialization, academic_unit_id, and salary_grade
 * for all faculty by parsing their existing `position` column text.
 *
 * Safe to re-run: only updates SST faculty (position starts with "SST" or "COS-*Teacher").
 * Does NOT touch admin/support staff records.
 */
class FacultySpecializationSeeder extends Seeder
{
    // ── Specialization → Department code mapping ──────────────────────────────

    private array $specToDept = [
        'Computer Science'       => 'CS',
        'Information Technology' => 'CS',
        'Integrated Science'     => 'PISES',
        'Physics'                => 'PISES',
        'Biology'                => 'BIOCHEM',
        'Chemistry'              => 'BIOCHEM',
        'English'                => 'ENG',
        'Filipino'               => 'FIL',
        'Mathematics'            => 'MATH',
        'Statistics'             => 'MATH',
        'Social Science'         => 'SOCSCI',
        'Economics'              => 'SOCSCI',
        'Values Education'       => 'PEHM',
        'Health'                 => 'PEHM',
        'PEHM'                   => 'PEHM',
        'AdTech'                 => 'PEHM',
        'Agriculture'            => 'PEHM',
        'Engineering'            => 'ENGRES',
        'Research'               => 'ENGRES',
        'Research & Engineering' => 'ENGRES',
    ];

    // ── SST level → salary grade ──────────────────────────────────────────────

    private array $sstToSg = [
        'I'   => 12,
        'II'  => 13,
        'III' => 14,
        'IV'  => 15,
        'V'   => 17,
    ];

    public function run(): void
    {
        // Preload lookup tables
        $sstPositions = SstPosition::pluck('id', 'code'); // e.g. 'SST-I' => 1
        $deptIds      = AcademicUnit::pluck('id', 'code'); // e.g. 'CS' => 3

        $users = DB::table('users')
            ->where('status', '<>', 'inactive')
            ->whereNotNull('position')
            ->where('position', '<>', '')
            ->get(['id', 'name', 'position']);

        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $pos = trim($user->position);

            // ── Parse SST positions ───────────────────────────────────────────
            if (preg_match('/SST[- ]*([IVX]+)/i', $pos, $m)) {
                $level = strtoupper(trim($m[1]));
                $sstId = $sstPositions["SST-{$level}"] ?? null;
                $sg    = $this->sstToSg[$level] ?? null;

                // Extract specialization: strip the SST-X prefix and trailing
                // designation text (e.g. "SSD Chief", "AUH", "CID Chief").
                // Use \x{2013}|\x{2014} to match Unicode en/em dashes with the u flag.
                $spec = preg_replace('/^SST[- ]*[IVX]+\s*(?:[-\x{2013}\x{2014}]|\s)\s*/iu', '', $pos);
                $spec = preg_replace('/[,\s]*(?:SSD Chief|CID Chief|AUH|OIC.*)?$/i', '', $spec);
                $spec = $this->normaliseSpec(trim($spec));

                $deptCode = $this->resolveDept($spec);
                $deptId   = $deptCode ? ($deptIds[$deptCode] ?? null) : null;

                DB::table('users')->where('id', $user->id)->update(array_filter([
                    'sst_position_id' => $sstId,
                    'specialization'  => $spec ?: null,
                    'academic_unit_id'=> $deptId,
                    'salary_grade'    => $sg,
                ], fn ($v) => ! is_null($v)));

                $this->command->line("  SST-{$level} | {$user->name} | {$spec} → " . ($deptCode ?? '?'));
                $updated++;
                continue;
            }

            // ── COS teachers ─────────────────────────────────────────────────
            if (preg_match('/COS[-\s]+(.+?)\s+Teacher/i', $pos, $m)) {
                $spec    = $this->normaliseSpec(trim($m[1]));
                $deptCode= $this->resolveDept($spec);
                $deptId  = $deptCode ? ($deptIds[$deptCode] ?? null) : null;

                DB::table('users')->where('id', $user->id)->update(array_filter([
                    'specialization'  => $spec ?: null,
                    'academic_unit_id'=> $deptId,
                ], fn ($v) => ! is_null($v)));

                $this->command->line("  COS    | {$user->name} | {$spec} → " . ($deptCode ?? '?'));
                $updated++;
                continue;
            }

            $skipped++;
        }

        $this->command->info("Faculty specialization seeded: {$updated} updated, {$skipped} non-faculty skipped.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Normalise free-text specialisation strings to canonical names.
     */
    private function normaliseSpec(string $raw): string
    {
        $map = [
            '/^integrated\s*sci(ence)?$/i'          => 'Integrated Science',
            '/^computer\s*sci(ence)?$/i'             => 'Computer Science',
            '/^info(rmation)?\s*tech(nology)?$/i'   => 'Information Technology',
            '/^math(ematics)?$/i'                    => 'Mathematics',
            '/^stat(istics)?$/i'                     => 'Statistics',
            '/^phys(ics)?$/i'                        => 'Physics',
            '/^bio(logy)?$/i'                        => 'Biology',
            '/^chem(istry)?$/i'                      => 'Chemistry',
            '/^eng(lish)?$/i'                        => 'English',
            '/^fil(ipino)?$/i'                       => 'Filipino',
            '/^soc(ial)?\s*sci(ence)?$/i'           => 'Social Science',
            '/^econ(omics)?$/i'                      => 'Economics',
            '/^values?\s*ed(ucation)?$/i'            => 'Values Education',
            '/^health$/i'                            => 'Health',
            '/^pehm$/i'                              => 'PEHM',
            '/^adtech$/i'                            => 'AdTech',
            '/^agri(culture)?$/i'                    => 'Agriculture',
            '/^engin(eering)?$/i'                    => 'Engineering',
            '/^research\s*&\s*engin(eering)?$/i'    => 'Research & Engineering',
            '/^research$/i'                          => 'Research',
        ];

        foreach ($map as $pattern => $canonical) {
            if (preg_match($pattern, $raw)) {
                return $canonical;
            }
        }

        return $raw; // Return as-is if no match — keeps the original text
    }

    /**
     * Resolve specialisation string to academic unit department code.
     */
    private function resolveDept(string $spec): ?string
    {
        foreach ($this->specToDept as $key => $code) {
            if (strcasecmp($spec, $key) === 0) {
                return $code;
            }
        }
        return null;
    }
}
