<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    /**
     * Seed the PSHS core subject catalog.
     * Based on PSHSS standard curriculum (Grades 7–12).
     *
     * load_units: hours contributed toward the 18-unit faculty load
     * sessions_per_week: how many class meetings per week
     * minutes_per_session: duration of each meeting
     */
    public function run(): void
    {
        $subjects = [
            // ─────────────────────────────────────────────────────────────────
            // GRADE 7
            // ─────────────────────────────────────────────────────────────────
            ['code' => 'MATH7',  'name' => 'Mathematics 7',             'grade_level' => 7,  'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'SCI7',   'name' => 'Integrated Science 7',      'grade_level' => 7,  'credit_units' => 4, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 5, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'ENG7',   'name' => 'English 7',                 'grade_level' => 7,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'FIL7',   'name' => 'Filipino 7',                'grade_level' => 7,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'SS7',    'name' => 'Social Science 7',          'grade_level' => 7,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'PEHM7',  'name' => 'PEHM 7',                   'grade_level' => 7,  'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 1,   'load_units' => 2, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],
            ['code' => 'ICT7',   'name' => 'Computer Science 7',        'grade_level' => 7,  'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 1.5, 'load_units' => 3, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],

            // ─────────────────────────────────────────────────────────────────
            // GRADE 8
            // ─────────────────────────────────────────────────────────────────
            ['code' => 'MATH8',  'name' => 'Mathematics 8',             'grade_level' => 8,  'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'PHY8',   'name' => 'Physics 8',                 'grade_level' => 8,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'first',  'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'CHEM8',  'name' => 'Chemistry 8',               'grade_level' => 8,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'second', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'BIO8',   'name' => 'Biology 8',                 'grade_level' => 8,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both',   'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'ENG8',   'name' => 'English 8',                 'grade_level' => 8,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'FIL8',   'name' => 'Filipino 8',                'grade_level' => 8,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'SS8',    'name' => 'Social Science 8',          'grade_level' => 8,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'PEHM8',  'name' => 'PEHM 8',                   'grade_level' => 8,  'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 1,   'load_units' => 2, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],
            ['code' => 'RES8',   'name' => 'Research 8',                'grade_level' => 8,  'credit_units' => 2, 'lecture_hours' => 2, 'lab_hours' => 0,   'load_units' => 2, 'subject_type' => 'research',    'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],
            ['code' => 'ICT8',   'name' => 'Computer Science 8',        'grade_level' => 8,  'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 1.5, 'load_units' => 3, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],

            // ─────────────────────────────────────────────────────────────────
            // GRADE 9
            // ─────────────────────────────────────────────────────────────────
            ['code' => 'MATH9',  'name' => 'Advanced Mathematics 9',    'grade_level' => 9,  'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'STAT9',  'name' => 'Statistics 9',              'grade_level' => 9,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'second', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'PHY9',   'name' => 'Physics 9',                 'grade_level' => 9,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'CHEM9',  'name' => 'Chemistry 9',               'grade_level' => 9,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'BIO9',   'name' => 'Biology 9',                 'grade_level' => 9,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'ENG9',   'name' => 'English 9',                 'grade_level' => 9,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'FIL9',   'name' => 'Filipino 9',                'grade_level' => 9,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'SS9',    'name' => 'Social Science 9',          'grade_level' => 9,  'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'RES9',   'name' => 'Research 9',                'grade_level' => 9,  'credit_units' => 2, 'lecture_hours' => 2, 'lab_hours' => 0,   'load_units' => 2, 'subject_type' => 'research',    'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],
            ['code' => 'PEHM9',  'name' => 'PEHM 9',                   'grade_level' => 9,  'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 1,   'load_units' => 2, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],

            // ─────────────────────────────────────────────────────────────────
            // GRADE 10
            // ─────────────────────────────────────────────────────────────────
            ['code' => 'MATH10', 'name' => 'Advanced Mathematics 10',   'grade_level' => 10, 'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'PHY10',  'name' => 'Physics 10',                'grade_level' => 10, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'CHEM10', 'name' => 'Chemistry 10',              'grade_level' => 10, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'BIO10',  'name' => 'Biology 10',                'grade_level' => 10, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'ENG10',  'name' => 'English 10',                'grade_level' => 10, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'FIL10',  'name' => 'Filipino 10',               'grade_level' => 10, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'SS10',   'name' => 'Social Science 10',         'grade_level' => 10, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'RES10',  'name' => 'Research 10',               'grade_level' => 10, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'research',    'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'PEHM10', 'name' => 'PEHM 10',                  'grade_level' => 10, 'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 1,   'load_units' => 2, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],

            // ─────────────────────────────────────────────────────────────────
            // GRADE 11
            // ─────────────────────────────────────────────────────────────────
            ['code' => 'PRECAL11','name' => 'Pre-Calculus 11',          'grade_level' => 11, 'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'lecture',  'semester' => 'first',  'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'CALC11', 'name' => 'Basic Calculus 11',         'grade_level' => 11, 'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'lecture',  'semester' => 'second', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'STAT11', 'name' => 'Statistics & Probability 11','grade_level' => 11,'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',  'semester' => 'second', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'PHY11',  'name' => 'Physics 11',                'grade_level' => 11, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'CHEM11', 'name' => 'Chemistry 11',              'grade_level' => 11, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'BIO11',  'name' => 'Biology 11',                'grade_level' => 11, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'ENG11',  'name' => 'English 11',                'grade_level' => 11, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'FIL11',  'name' => 'Filipino 11',               'grade_level' => 11, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'SS11',   'name' => 'Social Science 11',         'grade_level' => 11, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'RES11',  'name' => 'Research 11',               'grade_level' => 11, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'research',    'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],

            // ─────────────────────────────────────────────────────────────────
            // GRADE 12
            // ─────────────────────────────────────────────────────────────────
            ['code' => 'CALC12', 'name' => 'Calculus 12',               'grade_level' => 12, 'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'lecture',  'semester' => 'both', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'PHY12',  'name' => 'Physics 12',                'grade_level' => 12, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'CHEM12', 'name' => 'Chemistry 12',              'grade_level' => 12, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'BIO12',  'name' => 'Biology 12',                'grade_level' => 12, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 1.5, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'ENG12',  'name' => 'English 12',                'grade_level' => 12, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'FIL12',  'name' => 'Filipino 12',               'grade_level' => 12, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'SS12',   'name' => 'Social Science 12',         'grade_level' => 12, 'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0,   'load_units' => 3, 'subject_type' => 'lecture',     'semester' => 'both', 'sessions_per_week' => 3, 'minutes_per_session' => 60],
            ['code' => 'RES12',  'name' => 'Research 12 (Thesis)',      'grade_level' => 12, 'credit_units' => 4, 'lecture_hours' => 4, 'lab_hours' => 0,   'load_units' => 4, 'subject_type' => 'research',    'semester' => 'both', 'sessions_per_week' => 4, 'minutes_per_session' => 60],
            ['code' => 'PEHM12', 'name' => 'PEHM 12',                  'grade_level' => 12, 'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 1,   'load_units' => 2, 'subject_type' => 'lecture_lab', 'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 60],

            // ─────────────────────────────────────────────────────────────────
            // CROSS-GRADE ELECTIVES (grade_level = 0)
            // ─────────────────────────────────────────────────────────────────
            ['code' => 'ELEC-RO',  'name' => 'Robotics & Programming',  'grade_level' => 0,  'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 2,   'load_units' => 3, 'subject_type' => 'elective',    'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 90],
            ['code' => 'ELEC-ENV', 'name' => 'Environmental Science',   'grade_level' => 0,  'credit_units' => 2, 'lecture_hours' => 2, 'lab_hours' => 1,   'load_units' => 3, 'subject_type' => 'elective',    'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 90],
            ['code' => 'ELEC-ART', 'name' => 'Arts & Design',           'grade_level' => 0,  'credit_units' => 2, 'lecture_hours' => 1, 'lab_hours' => 2,   'load_units' => 3, 'subject_type' => 'elective',    'semester' => 'both', 'sessions_per_week' => 2, 'minutes_per_session' => 90],
        ];

        foreach ($subjects as $subject) {
            DB::table('subjects')->updateOrInsert(
                ['code' => $subject['code']],
                array_merge($subject, [
                    'description' => null,
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ])
            );
        }

        $this->command->info('Subjects seeded (' . count($subjects) . ' subjects).');
    }
}
