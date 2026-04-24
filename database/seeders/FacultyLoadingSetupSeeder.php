<?php

namespace Database\Seeders;

use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\GradeLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the fixed vocabulary tables for the Faculty Loading module.
 *
 *  - academic_units        — 9 subject-area departments (from S.Y. 2025–2026 FL PDF)
 *  - grade_levels          — Grades 7–12 (fixed, never changes)
 *  - designation_categories + designations — full PDF taxonomy
 *  - subjects.academic_unit_id — links existing subjects to their owning department
 *
 * Safe to re-run: truncates and re-seeds the vocabulary tables on each run.
 * (Sections are already managed per-school-year in the legacy system.)
 */
class FacultyLoadingSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAcademicUnits();
        $this->seedGradeLevels();
        $this->seedDesignations();
        $this->linkSubjectsToDepartments();
    }

    // ── Academic Units (Subject-Area Departments) ─────────────────────────────
    //
    // Source: "AcadUnit" column in Faculty Loading 2025-2026 PDF.
    // All 9 departments use unit_type = 'department' since they span all grade levels.

    private function seedAcademicUnits(): void
    {
        // Null-out FKs so the delete below doesn't violate constraints
        DB::table('grade_levels')->update(['academic_unit_id' => null]);
        DB::table('subjects')->update(['academic_unit_id' => null]);

        // Remove all previous units (JHS/SHS/SST/ADMIN) — use delete not truncate
        // because MySQL blocks TRUNCATE when any FK references the table.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        AcademicUnit::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $departments = [
            ['code' => 'CS',     'name' => 'Computer Science',  'sort_order' => 1],
            ['code' => 'PISES',  'name' => 'PISES',             'sort_order' => 2],
            ['code' => 'ENG',    'name' => 'English',           'sort_order' => 3],
            ['code' => 'FIL',    'name' => 'Filipino',          'sort_order' => 4],
            ['code' => 'MATH',   'name' => 'Mathematics',       'sort_order' => 5],
            ['code' => 'BIOCHEM','name' => 'BioChem',           'sort_order' => 6],
            ['code' => 'SOCSCI', 'name' => 'Social Science',    'sort_order' => 7],
            ['code' => 'PEHM',   'name' => 'PEHM-VE',          'sort_order' => 8],
            ['code' => 'ENGRES', 'name' => 'Engres',            'sort_order' => 9],
        ];

        foreach ($departments as $dept) {
            AcademicUnit::create(array_merge($dept, [
                'unit_type' => 'department',
                'is_active' => true,
            ]));
        }
    }

    // ── Grade Levels ──────────────────────────────────────────────────────────
    //
    // All departments span Grades 7–12 so academic_unit_id is left null here.

    private function seedGradeLevels(): void
    {
        $levels = [
            ['grade' => 7,  'label' => 'Grade 7',  'sort_order' => 1],
            ['grade' => 8,  'label' => 'Grade 8',  'sort_order' => 2],
            ['grade' => 9,  'label' => 'Grade 9',  'sort_order' => 3],
            ['grade' => 10, 'label' => 'Grade 10', 'sort_order' => 4],
            ['grade' => 11, 'label' => 'Grade 11', 'sort_order' => 5],
            ['grade' => 12, 'label' => 'Grade 12', 'sort_order' => 6],
        ];

        foreach ($levels as $level) {
            GradeLevel::updateOrCreate(
                ['grade' => $level['grade']],
                array_merge($level, ['academic_unit_id' => null])
            );
        }
    }

    // ── Designation Categories & Designations ─────────────────────────────────
    //
    // Based on the Faculty Loading 2025–2026 PDF designation taxonomy.
    // Categories are from the PDF section headers; load_units match PDF values.

    private function seedDesignations(): void
    {
        // Clear designations then categories (FK: designations.designation_category_id)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('designations')->truncate();
        DB::table('designation_categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Categories ────────────────────────────────────────────────────────

        $auh = $this->cat('AUH',         'Academic Unit Head',      'Academic Unit Head designation for each subject department',  1);
        $stu = $this->cat('STUDENT_ORG', 'Student Organization',    'Student organisation adviser roles',                          2);
        $ala = $this->cat('ALA',         'ALA Advisory',            'Academic Learning Associations advisory roles',               3);
        $crd = $this->cat('COORD',       'Coordinatorship',         'Campus coordination and program officer roles',               4);
        $sco = $this->cat('SCO',         'Student Conduct Officer', 'Student conduct and discipline officer roles',                5);
        $hra = $this->cat('HR_ADV',      'HR Advisory',             'Homeroom advisory for Grades 7–10',                          6);
        $hac = $this->cat('HR_ACAD',     'HR/Academic Advisory',    'Homeroom/Academic advisory for Grades 11–12',                7);
        $res = $this->cat('RESEARCH',    'Research Advisory',       'Research group and thesis advisory roles',                   8);
        $scl = $this->cat('SCALE',       'SCALE Advisory',          'SCALE section advisory for Senior High',                     9);
        $spc = $this->cat('SPECIAL',     'Special Assignment',      'Special campus assignment roles',                           10);
        $sup = $this->cat('SUPV',        'Supervisory',             'Supervisory roles with high load units',                    11);
        $tch = $this->cat('TEACHING',    'Teaching Load',           'Regular classroom teaching assignments',                    12);

        // ── Academic Unit Head (1 per department, 6 units) ────────────────────

        $this->desigs($auh, [
            ['AUH-CS',     'Academic Unit Head - Computer Science',  6, true,  1],
            ['AUH-PISES',  'Academic Unit Head - PISES',             6, true,  2],
            ['AUH-ENG',    'Academic Unit Head - English',           6, true,  3],
            ['AUH-FIL',    'Academic Unit Head - Filipino',          6, true,  4],
            ['AUH-MATH',   'Academic Unit Head - Mathematics',       6, true,  5],
            ['AUH-BIOCHEM','Academic Unit Head - BioChem',           6, true,  6],
            ['AUH-SOCSCI', 'Academic Unit Head - Social Science',    6, true,  7],
            ['AUH-PEHM',   'Academic Unit Head - PEHM-VE',          6, true,  8],
            ['AUH-ENGRES', 'Academic Unit Head - Engres',            6, true,  9],
        ]);

        // ── Student Organization advisers ─────────────────────────────────────

        $this->desigs($stu, [
            ['SSG',     'SSG Adviser',         3, false, 1],
            ['CASS',    'CASS Adviser',         2, false, 2],
            ['SCUAA',   'DYP-SCUAA Adviser',   2, false, 3],
            ['SCOUT',   'Scout Adviser',        1, false, 4],
        ]);

        // ── ALA Advisory (13 academic/interest clubs) ─────────────────────────

        $this->desigs($ala, [
            ['ALA-MATH',   'Mathematics Club Adviser',           3, false,  1],
            ['ALA-SCI',    'Science Club Adviser',               3, false,  2],
            ['ALA-ENG',    'English Club Adviser',               3, false,  3],
            ['ALA-FIL',    'Filipino Club Adviser',              3, false,  4],
            ['ALA-SS',     'Social Science Club Adviser',        3, false,  5],
            ['ALA-CS',     'Computer Science Club Adviser',      3, false,  6],
            ['ALA-BIOCHEM','BioChem Club Adviser',               3, false,  7],
            ['ALA-PEHM',   'PEHM Club Adviser',                  2, false,  8],
            ['ALA-RES',    'Research Club Adviser',              2, false,  9],
            ['ALA-DRAMA',  'Drama Club Adviser',                 2, false, 10],
            ['ALA-MUSIC',  'Music Club Adviser',                 2, false, 11],
            ['ALA-DEBATE', 'Debate Club Adviser',                2, false, 12],
            ['ALA-ENV',    'Environmental Club Adviser',         2, false, 13],
        ]);

        // ── Coordinatorship ───────────────────────────────────────────────────

        $this->desigs($crd, [
            ['COORD-RESEARCH',   'Research Coordinator',                  4, false,  1],
            ['COORD-LIBRARY',    'Library Coordinator',                   4, false,  2],
            ['COORD-GUIDANCE',   'Guidance Coordinator',                  4, false,  3],
            ['COORD-HEALTH',     'Health & Wellness Coordinator',         4, false,  4],
            ['COORD-TECH',       'Technology Coordinator',                3, false,  5],
            ['COORD-ACTIVITIES', 'Activities Coordinator',               3, false,  6],
            ['COORD-SCHOLAR',    'Scholarship Coordinator',               3, false,  7],
            ['COORD-DOST',       'DOST Scholarship Coordinator',          3, false,  8],
            ['COORD-IPCR',       'IPCR Coordinator',                      3, false,  9],
            ['COORD-GENDER',     'Gender & Development Coordinator',      3, false, 10],
            ['COORD-SAFETY',     'Safety & Security Coordinator',         2, false, 11],
            ['COORD-CAMPUS',     'Campus Journalist Coordinator',         2, false, 12],
            ['COORD-DORMITORY',  'Dormitory Coordinator',                 3, false, 13],
            ['COORD-NUTRITION',  'Nutrition Coordinator',                 2, false, 14],
            ['COORD-DISASTER',   'Disaster Risk Reduction Coordinator',   2, false, 15],
        ]);

        // ── Student Conduct Officer ───────────────────────────────────────────

        $this->desigs($sco, [
            ['SCO-JHS', 'Student Conduct Officer (JHS)', 6, false, 1],
            ['SCO-SHS', 'Student Conduct Officer (SHS)', 5, false, 2],
        ]);

        // ── HR Advisory (homeroom — Grades 7–10, 3 units) ────────────────────

        $this->desigs($hra, [
            ['HRA-G7A',  'HR Adviser — G7 Section 1',  3, false,  1],
            ['HRA-G7B',  'HR Adviser — G7 Section 2',  3, false,  2],
            ['HRA-G7C',  'HR Adviser — G7 Section 3',  3, false,  3],
            ['HRA-G7D',  'HR Adviser — G7 Section 4',  3, false,  4],
            ['HRA-G8A',  'HR Adviser — G8 Section 1',  3, false,  5],
            ['HRA-G8B',  'HR Adviser — G8 Section 2',  3, false,  6],
            ['HRA-G8C',  'HR Adviser — G8 Section 3',  3, false,  7],
            ['HRA-G8D',  'HR Adviser — G8 Section 4',  3, false,  8],
            ['HRA-G9A',  'HR Adviser — G9 Section 1',  3, false,  9],
            ['HRA-G9B',  'HR Adviser — G9 Section 2',  3, false, 10],
            ['HRA-G9C',  'HR Adviser — G9 Section 3',  3, false, 11],
            ['HRA-G9D',  'HR Adviser — G9 Section 4',  3, false, 12],
            ['HRA-G10A', 'HR Adviser — G10 Section 1', 3, false, 13],
            ['HRA-G10B', 'HR Adviser — G10 Section 2', 3, false, 14],
            ['HRA-G10C', 'HR Adviser — G10 Section 3', 3, false, 15],
            ['HRA-G10D', 'HR Adviser — G10 Section 4', 3, false, 16],
        ]);

        // ── HR/Academic Advisory (homeroom — Grades 11–12, 3 units) ──────────

        $this->desigs($hac, [
            ['HAC-G11A',  'HR/Academic Adviser — G11 Section 1', 3, false, 1],
            ['HAC-G11B',  'HR/Academic Adviser — G11 Section 2', 3, false, 2],
            ['HAC-G11C',  'HR/Academic Adviser — G11 Section 3', 3, false, 3],
            ['HAC-G12A',  'HR/Academic Adviser — G12 Section 1', 3, false, 4],
            ['HAC-G12B',  'HR/Academic Adviser — G12 Section 2', 3, false, 5],
            ['HAC-G12C',  'HR/Academic Adviser — G12 Section 3', 3, false, 6],
        ]);

        // ── Research Advisory (1–2 units per group) ───────────────────────────

        $this->desigs($res, [
            ['RES-G7',  'Research Adviser — Grade 7',  2, false, 1],
            ['RES-G8',  'Research Adviser — Grade 8',  2, false, 2],
            ['RES-G9',  'Research Adviser — Grade 9',  2, false, 3],
            ['RES-G10', 'Research Adviser — Grade 10', 2, false, 4],
            ['RES-G11', 'Research Adviser — Grade 11', 1, false, 5],
            ['RES-G12', 'Research Adviser — Grade 12 (Thesis)', 1, false, 6],
        ]);

        // ── SCALE Advisory (per SHS section, 3 units) ────────────────────────

        $this->desigs($scl, [
            ['SCALE-G11A', 'SCALE Adviser — G11 Section 1', 3, false, 1],
            ['SCALE-G11B', 'SCALE Adviser — G11 Section 2', 3, false, 2],
            ['SCALE-G11C', 'SCALE Adviser — G11 Section 3', 3, false, 3],
            ['SCALE-G12A', 'SCALE Adviser — G12 Section 1', 3, false, 4],
            ['SCALE-G12B', 'SCALE Adviser — G12 Section 2', 3, false, 5],
            ['SCALE-G12C', 'SCALE Adviser — G12 Section 3', 3, false, 6],
        ]);

        // ── Special Assignment (2–3 units) ────────────────────────────────────

        $this->desigs($spc, [
            ['SA-CLINIC',   'School Clinic In-Charge',         3, false, 1],
            ['SA-CANTEEN',  'Canteen In-Charge',               2, false, 2],
            ['SA-RECORDS',  'Records Custodian',               2, false, 3],
            ['SA-PROPERTY', 'Property Custodian',              2, false, 4],
            ['SA-CASHIER',  'Cashier In-Charge',               3, false, 5],
            ['SA-MEDIA',    'School Publication In-Charge',    2, false, 6],
        ]);

        // ── Supervisory (9–15 units) ──────────────────────────────────────────

        $this->desigs($sup, [
            ['SUP-ASST-PRINCIPAL', 'Assistant Principal',              15, false, 1],
            ['SUP-DEAN',           'Dean of Students',                  12, false, 2],
            ['SUP-CID-CHIEF',      'CID Chief',                         12, false, 3],
            ['SUP-ADMIN-OFFICER',  'Administrative Officer',             9, false, 4],
        ]);

        // ── Teaching Load ─────────────────────────────────────────────────────

        $this->desigs($tch, [
            ['TL-REGULAR',    'Regular Teaching Load',   0, false, 1],
            ['TL-OVERLOAD',   'Overload Teaching',       0, false, 2],
            ['TL-SPECIAL',    'Special/Elective Class',  0, false, 3],
        ]);
    }

    // ── Subject → Department Linking ─────────────────────────────────────────
    //
    // Maps each subject code prefix to its owning academic unit department.

    private function linkSubjectsToDepartments(): void
    {
        $units = AcademicUnit::pluck('id', 'code');

        // code-prefix → department code
        $mapping = [
            // Mathematics
            'MATH'    => 'MATH',
            'STAT'    => 'MATH',
            'CALC'    => 'MATH',
            'PRECAL'  => 'MATH',
            // Physics / Integrated Science (PISES)
            'SCI'     => 'PISES',
            'PHY'     => 'PISES',
            // Biology & Chemistry (BioChem)
            'CHEM'    => 'BIOCHEM',
            'BIO'     => 'BIOCHEM',
            // English
            'ENG'     => 'ENG',
            // Filipino
            'FIL'     => 'FIL',
            // Social Science
            'SS'      => 'SOCSCI',
            // PEHM-VE
            'PEHM'    => 'PEHM',
            'VE'      => 'PEHM',
            'PE'      => 'PEHM',
            // Computer Science
            'ICT'     => 'CS',
            'CS'      => 'CS',
            'ELEC-RO' => 'CS',
            // PEHM-VE electives
            'ELEC-ART' => 'PEHM',
            // PISES electives
            'ELEC-ENV' => 'PISES',
            // Engres (Research)
            'RES'     => 'ENGRES',
            'ENGRES'  => 'ENGRES',
        ];

        $subjects = DB::table('subjects')->get(['id', 'code']);

        foreach ($subjects as $subject) {
            $deptCode = null;

            // Try longest-prefix match first (e.g. ELEC-RO before EL)
            foreach ($mapping as $prefix => $dept) {
                if (str_starts_with(strtoupper($subject->code), strtoupper($prefix))) {
                    $deptCode = $dept;
                    break;
                }
            }

            if ($deptCode && isset($units[$deptCode])) {
                DB::table('subjects')
                    ->where('id', $subject->id)
                    ->update(['academic_unit_id' => $units[$deptCode]]);
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cat(string $code, string $name, string $desc, int $sort): DesignationCategory
    {
        return DesignationCategory::create([
            'code'        => $code,
            'name'        => $name,
            'description' => $desc,
            'sort_order'  => $sort,
            'is_active'   => true,
        ]);
    }

    /**
     * @param array<array{0:string, 1:string, 2:int|float, 3:bool, 4:int}> $rows
     *   Each row: [code, name, load_units, requires_unit, sort_order]
     */
    private function desigs(DesignationCategory $cat, array $rows): void
    {
        foreach ($rows as [$code, $name, $load, $requiresUnit, $sort]) {
            Designation::create([
                'designation_category_id' => $cat->id,
                'code'         => $code,
                'name'         => $name,
                'load_units'   => $load,
                'requires_unit'=> $requiresUnit,
                'max_holders'  => null,
                'sort_order'   => $sort,
                'is_active'    => true,
            ]);
        }
    }
}
