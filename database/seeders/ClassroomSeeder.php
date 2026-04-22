<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        $classrooms = [
            // ── Lecture / General Classrooms ──────────────────────────────────
            ['name' => 'Science Hall 101', 'code' => 'SH-101', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Science Hall 102', 'code' => 'SH-102', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Science Hall 103', 'code' => 'SH-103', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Science Hall 104', 'code' => 'SH-104', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Science Hall 201', 'code' => 'SH-201', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 2],
            ['name' => 'Science Hall 202', 'code' => 'SH-202', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 2],
            ['name' => 'Science Hall 203', 'code' => 'SH-203', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 2],
            ['name' => 'Science Hall 204', 'code' => 'SH-204', 'classroom_type' => 'lecture',     'capacity' => 45, 'building' => 'Science Hall', 'floor' => 2],

            // ── Science Laboratories ──────────────────────────────────────────
            ['name' => 'Physics Laboratory 1',   'code' => 'PHL-1', 'classroom_type' => 'physics_lab',   'capacity' => 35, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Physics Laboratory 2',   'code' => 'PHL-2', 'classroom_type' => 'physics_lab',   'capacity' => 35, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Chemistry Laboratory 1', 'code' => 'CHL-1', 'classroom_type' => 'chemistry_lab', 'capacity' => 35, 'building' => 'Science Hall', 'floor' => 2],
            ['name' => 'Chemistry Laboratory 2', 'code' => 'CHL-2', 'classroom_type' => 'chemistry_lab', 'capacity' => 35, 'building' => 'Science Hall', 'floor' => 2],
            ['name' => 'Biology Laboratory 1',   'code' => 'BIO-1', 'classroom_type' => 'biology_lab',   'capacity' => 35, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Biology Laboratory 2',   'code' => 'BIO-2', 'classroom_type' => 'biology_lab',   'capacity' => 35, 'building' => 'Science Hall', 'floor' => 1],
            ['name' => 'Integrated Science Lab', 'code' => 'ISL-1', 'classroom_type' => 'science_lab',   'capacity' => 40, 'building' => 'Science Hall', 'floor' => 1],

            // ── Mathematics Lab ───────────────────────────────────────────────
            ['name' => 'Mathematics Laboratory', 'code' => 'MATL-1', 'classroom_type' => 'mathematics_lab', 'capacity' => 40, 'building' => 'Science Hall', 'floor' => 2],

            // ── ICT / Computer Laboratories ───────────────────────────────────
            ['name' => 'ICT Laboratory 1', 'code' => 'ICT-1', 'classroom_type' => 'ict_lab', 'capacity' => 40, 'building' => 'ICT Building', 'floor' => 1],
            ['name' => 'ICT Laboratory 2', 'code' => 'ICT-2', 'classroom_type' => 'ict_lab', 'capacity' => 40, 'building' => 'ICT Building', 'floor' => 1],

            // ── Language Laboratory ───────────────────────────────────────────
            ['name' => 'Language Laboratory', 'code' => 'LANG-1', 'classroom_type' => 'language_lab', 'capacity' => 40, 'building' => 'Academic Building', 'floor' => 1],

            // ── Gymnasium / Covered Court ─────────────────────────────────────
            ['name' => 'Gymnasium',     'code' => 'GYM-1',   'classroom_type' => 'gymnasium',  'capacity' => 200, 'building' => 'Gymnasium',        'floor' => 1],
            ['name' => 'Covered Court', 'code' => 'COURT-1', 'classroom_type' => 'gymnasium',  'capacity' => 150, 'building' => 'Covered Court',    'floor' => 1],

            // ── Auditorium ────────────────────────────────────────────────────
            ['name' => 'Auditorium',    'code' => 'AUD-1',   'classroom_type' => 'auditorium', 'capacity' => 300, 'building' => 'Main Building',    'floor' => 1],
        ];

        foreach ($classrooms as $room) {
            DB::table('classrooms')->updateOrInsert(
                ['code' => $room['code']],
                array_merge($room, [
                    'room_id'      => null,
                    'is_available' => true,
                    'remarks'      => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ])
            );
        }

        $this->command->info('Classrooms seeded (' . count($classrooms) . ' rooms).');
    }
}
