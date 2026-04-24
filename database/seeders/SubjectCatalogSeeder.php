<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the subjects table for the current faculty loading catalog.
 *
 * Replaces all existing subject records entirely.
 *
 * Columns populated:
 *   code, name, grade_level, load_units, subject_type,
 *   academic_unit_id, semester, is_active
 *
 * grade_level = 0 → cross-grade elective (offered to both G11 & G12).
 */
class SubjectCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve department IDs once
        $u = DB::table('academic_units')->pluck('id', 'code');

        $cs     = $u['CS'];
        $pises  = $u['PISES'];
        $biochem= $u['BIOCHEM'];
        $eng    = $u['ENG'];
        $fil    = $u['FIL'];
        $math   = $u['MATH'];
        $socsci = $u['SOCSCI'];
        $pehm   = $u['PEHM'];
        $engres = $u['ENGRES'];

        // Wipe existing subjects (disable FK checks)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('subjects')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Format: [code, name, grade, load_units, type, dept_id] ──────────

        $subjects = [

            // ── Computer Science ─────────────────────────────────────────────
            ['CS1',     'Computer Science 1', 7,  3, 'lecture', $cs],
            ['CS2',     'Computer Science 2', 8,  3, 'lecture', $cs],
            ['CS3',     'Computer Science 3', 9,  3, 'lecture', $cs],
            ['CS4',     'Computer Science 4', 10, 3, 'lecture', $cs],

            // ── PISES (Integrated Science, Earth Science, Physics) ───────────
            ['IS1',    'Integrated Science 1', 7,  5, 'lecture_lab', $pises],
            ['ESCI',   'Earth Science',         8,  3, 'lecture',     $pises],
            ['PHY1G8', 'Physics 1 (G8)',        8,  3, 'lecture_lab', $pises],
            ['PHY1G9', 'Physics 1 (G9)',        9,  3, 'lecture_lab', $pises],
            ['PHY2',   'Physics 2',             10, 3, 'lecture_lab', $pises],
            ['PHY3L2', 'Physics 3 (Level 2)',   11, 5, 'lecture_lab', $pises],
            ['PHY4L2', 'Physics 4 (Level 2)',   12, 5, 'lecture_lab', $pises],

            // ── BioChem ──────────────────────────────────────────────────────
            ['BIO1G8',  'Biology 1 (G8)',         8,  3, 'lecture_lab', $biochem],
            ['BIO1G9',  'Biology 1 (G9)',         9,  3, 'lecture_lab', $biochem],
            ['BIO2',    'Biology 2',              10, 3, 'lecture_lab', $biochem],
            ['BIO3L2',  'Biology 3 (Level 2)',    11, 5, 'lecture_lab', $biochem],
            ['BIO4L2',  'Biology 4 (Level 2)',    12, 5, 'lecture_lab', $biochem],
            ['CHEM1G8', 'Chemistry 1 (G8)',        8, 3, 'lecture_lab', $biochem],
            ['CHEM1G9', 'Chemistry 1 (G9)',        9, 3, 'lecture_lab', $biochem],
            ['CHEM2',   'Chemistry 2',            10, 3, 'lecture_lab', $biochem],
            ['CHEM3L2', 'Chemistry 3 (Level 2)',  11, 5, 'lecture_lab', $biochem],
            ['CHEM4L2', 'Chemistry 4 (Level 2)',  12, 5, 'lecture_lab', $biochem],

            // ── English ──────────────────────────────────────────────────────
            ['ENG1',  'English 1',  7,  4, 'lecture', $eng],
            ['ENG2',  'English 2',  8,  4, 'lecture', $eng],
            ['ENG3',  'English 3',  9,  3, 'lecture', $eng],
            ['ENG4',  'English 4',  10, 3, 'lecture', $eng],
            ['ENG5',  'English 5',  11, 3, 'lecture', $eng],
            ['ENG6',  'English 6',  12, 3, 'lecture', $eng],

            // ── Filipino ─────────────────────────────────────────────────────
            ['FIL1',  'Filipino 1', 7,  3, 'lecture', $fil],
            ['FIL2',  'Filipino 2', 8,  3, 'lecture', $fil],
            ['FIL3',  'Filipino 3', 9,  3, 'lecture', $fil],
            ['FIL4',  'Filipino 4', 10, 3, 'lecture', $fil],
            ['FIL5',  'Filipino 5', 11, 3, 'lecture', $fil],
            ['FIL6',  'Filipino 6', 12, 3, 'lecture', $fil],

            // ── Mathematics ──────────────────────────────────────────────────
            ['MATH1',   'Mathematics 1',           7,  5, 'lecture', $math],
            ['MATH2',   'Mathematics 2',           8,  3, 'lecture', $math],
            ['MATH3G8', 'Mathematics 3 (G8)',      8,  3, 'lecture', $math],
            ['MATH3G9', 'Mathematics 3 (G9)',      9,  3, 'lecture', $math],
            ['MATH4',   'Mathematics 4',           10, 4, 'lecture', $math],
            ['MATH5L1', 'Mathematics 5 (Level 1)', 11, 3, 'lecture', $math],
            ['MATH5L2', 'Mathematics 5 (Level 2)', 11, 3, 'lecture', $math],
            ['MATH6L1', 'Mathematics 6 (Level 1)', 12, 3, 'lecture', $math],
            ['MATH6L2', 'Mathematics 6 (Level 2)', 12, 3, 'lecture', $math],

            // ── Social Science ───────────────────────────────────────────────
            ['SS1',  'Social Science 1', 7,  3, 'lecture', $socsci],
            ['SS2',  'Social Science 2', 8,  3, 'lecture', $socsci],
            ['SS3',  'Social Science 3', 9,  3, 'lecture', $socsci],
            ['SS4',  'Social Science 4', 10, 3, 'lecture', $socsci],
            ['SS5',  'Social Science 5', 11, 3, 'lecture', $socsci],
            ['SS6',  'Social Science 6', 12, 3, 'lecture', $socsci],

            // ── PEHM (Health, Music, PE, Values Education) ───────────────────
            ['HLT1',  'Health 1',              7,  4, 'lecture', $pehm],
            ['HLT2',  'Health 2',              8,  4, 'lecture', $pehm],
            ['HLT3',  'Health 3',              9,  4, 'lecture', $pehm],
            ['HLT4',  'Health 4',              10, 4, 'lecture', $pehm],
            ['MUS1',  'Music 1',               7,  1, 'lecture', $pehm],
            ['MUS2',  'Music 2',               8,  1, 'lecture', $pehm],
            ['MUS3',  'Music 3',               9,  1, 'lecture', $pehm],
            ['MUS4',  'Music 4',               10, 1, 'lecture', $pehm],
            ['PE1',   'Physical Education 1',  7,  1, 'lecture', $pehm],
            ['PE2',   'Physical Education 2',  8,  1, 'lecture', $pehm],
            ['PE3',   'Physical Education 3',  9,  1, 'lecture', $pehm],
            ['PE4',   'Physical Education 4',  10, 1, 'lecture', $pehm],
            ['VE1',   'Values Education 1',    7,  2, 'lecture', $pehm],
            ['VE2',   'Values Education 2',    8,  2, 'lecture', $pehm],
            ['VE3',   'Values Education 3',    9,  2, 'lecture', $pehm],
            ['VE4',   'Values Education 4',    10, 2, 'lecture', $pehm],

            // ── Engineering & Research (AdTech, STAT, STEM Research) ─────────
            ['ADTECH1',  'AdTech 1',          7,  3, 'lecture',  $engres],
            ['ADTECH2',  'AdTech 2',          8,  3, 'lecture',  $engres],
            ['STAT1',    'Statistics 1',      9,  3, 'lecture',  $engres],
            ['STEMRES1', 'STEM Research 1',   10, 3, 'research', $biochem],
            ['STEMRES2', 'STEM Research 2',   11, 3, 'research', $engres],
            ['STEMRES3', 'STEM Research 3',   12, 3, 'research', $engres],

            // ── G10 Electives ─────────────────────────────────────────────────
            ['ELEC-DATASCI',     'Elective: Data Science (AYP)',                               10, 3, 'elective', $engres],
            ['ELEC-MICROBIO',    'Elective: Microbiology and Basic Molecular Techniques (AYP)',10, 3, 'elective', $biochem],
            ['ELEC-FIELDSMP',    'Elective: Field Sampling Techniques (AYP)',                  10, 3, 'elective', $engres],
            ['ELEC-FIELDSMP-BIO','Elective: Field Sampling Techniques (AYP)',                  10, 3, 'elective', $biochem],
            ['ELEC-IPR',         'Elective: Intellectual Property Rights',                     10, 3, 'elective', $pises],
            ['ELEC-WELLBEING',   'Elective: The Science of Well-being (AYP)',                  10, 3, 'elective', $pehm],
            ['ELEC-PHILBIO',     'Elective: Philippine Biodiversity (AYP)',                    10, 3, 'elective', $biochem],
            ['ELEC-PHILSCI',     'Elective: Philosophy of Science (AYP)',                      10, 3, 'elective', $socsci],

            // ── G11 & G12 Electives (grade 0 = cross-grade) ──────────────────
            ['ELEC-AGRI',  'Elective: Agriculture',                0, 5, 'elective', $engres],
            ['ELEC-BIO3',  'Elective: Biology 3',                  0, 5, 'elective', $biochem],
            ['ELEC-CHEM4', 'Elective: Chemistry 4',                0, 5, 'elective', $biochem],
            ['ELEC-CS5',   'Elective: Computer Science 5',         0, 5, 'elective', $cs],
            ['ELEC-ENGR',  'Elective: Engineering',                0, 5, 'elective', $engres],
            ['ELEC-DMT',   'Elective: Design and Make Technology',  0, 5, 'elective', $engres],

            // ── G11-only Electives ────────────────────────────────────────────
            ['ELEC-CHEM3-G11', 'Elective: Chemistry 3', 11, 5, 'elective', $biochem],
            ['ELEC-PHY3',      'Elective: Physics 3',   11, 5, 'elective', $pises],

            // ── G12-only Electives ────────────────────────────────────────────
            ['ELEC-BIO4',      'Elective: Biology 4',   12, 5, 'elective', $biochem],
            ['ELEC-CHEM3-G12', 'Elective: Chemistry 3', 12, 5, 'elective', $biochem],
        ];

        $now  = now();
        $rows = [];

        foreach ($subjects as [$code, $name, $grade, $load, $type, $deptId]) {
            $rows[] = [
                'code'                => $code,
                'name'                => $name,
                'grade_level'         => $grade,
                'load_units'          => $load,
                'credit_units'        => min((int) $load, 10),
                'lecture_hours'       => $load,
                'lab_hours'           => 0,
                'subject_type'        => $type,
                'academic_unit_id'    => $deptId,
                'semester'            => 'both',
                'sessions_per_week'   => 1,
                'minutes_per_session' => 60,
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        DB::table('subjects')->insert($rows);

        $this->command->info('Subjects seeded: ' . count($rows));
    }
}
