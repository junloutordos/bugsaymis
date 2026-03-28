<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolYearSeeder extends Seeder
{
    public function run(): void
    {
        $years = [
            [
                'name'       => '2024-2025',
                'start_date' => '2024-06-01',
                'end_date'   => '2025-03-31',
                'is_current' => false,
                'status'     => 'archived',
            ],
            [
                'name'       => '2025-2026',
                'start_date' => '2025-06-01',
                'end_date'   => '2026-03-31',
                'is_current' => true,
                'status'     => 'active',
            ],
        ];

        foreach ($years as $year) {
            $sy = DB::table('school_years')->updateOrInsert(
                ['name' => $year['name']],
                array_merge($year, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Seed academic terms for 2025-2026
        $syId = DB::table('school_years')->where('name', '2025-2026')->value('id');

        $terms = [
            [
                'school_year_id' => $syId,
                'name'           => '1st Semester',
                'term_type'      => '1st_semester',
                'start_date'     => '2025-06-01',
                'end_date'       => '2025-10-31',
                'is_current'     => false,
            ],
            [
                'school_year_id' => $syId,
                'name'           => '2nd Semester',
                'term_type'      => '2nd_semester',
                'start_date'     => '2025-11-01',
                'end_date'       => '2026-03-31',
                'is_current'     => true,
            ],
        ];

        foreach ($terms as $term) {
            DB::table('academic_terms')->updateOrInsert(
                [
                    'school_year_id' => $term['school_year_id'],
                    'term_type'      => $term['term_type'],
                ],
                array_merge($term, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Also seed terms for 2024-2025
        $syId2 = DB::table('school_years')->where('name', '2024-2025')->value('id');

        $terms2 = [
            [
                'school_year_id' => $syId2,
                'name'           => '1st Semester',
                'term_type'      => '1st_semester',
                'start_date'     => '2024-06-01',
                'end_date'       => '2024-10-31',
                'is_current'     => false,
            ],
            [
                'school_year_id' => $syId2,
                'name'           => '2nd Semester',
                'term_type'      => '2nd_semester',
                'start_date'     => '2024-11-01',
                'end_date'       => '2025-03-31',
                'is_current'     => false,
            ],
        ];

        foreach ($terms2 as $term) {
            DB::table('academic_terms')->updateOrInsert(
                [
                    'school_year_id' => $term['school_year_id'],
                    'term_type'      => $term['term_type'],
                ],
                array_merge($term, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('School years and academic terms seeded.');
    }
}
