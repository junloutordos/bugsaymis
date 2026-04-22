<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SstPositionSeeder extends Seeder
{
    /**
     * Seed SST (Science and Technology Teacher) position levels.
     *
     * Positions SST-I through SST-V correspond to DBM Salary Grades
     * 12 through 17. All levels follow the same full-load threshold
     * of 18 units per BOT Resolution No. 2025-05-80, but differ in
     * typical assignment profiles and expected minimum teaching loads.
     *
     * Load caps (per resolution):
     *   - Max administrative load : 15 units
     *   - Max research advisory   :  5 units
     *   - Full load threshold     : 18 units
     *   - Max overload allowed    :  6 units (before Campus Director approval)
     */
    public function run(): void
    {
        $now = now();

        $positions = [
            [
                'code'               => 'SST-I',
                'name'               => 'Science and Technology Teacher I',
                'salary_grade'       => 12,
                'full_load_threshold'=> 18.00,
                'min_teaching_units' => 9.00,   // entry-level: primarily teaching
                'max_admin_units'    => 15.00,
                'max_research_units' => 5.00,
                'overload_threshold' => 18.00,
                'max_overload_units' => 6.00,
                'description'        => 'Entry-level SST. Expected to focus primarily on classroom instruction '
                                      . 'with limited administrative and research responsibilities.',
            ],
            [
                'code'               => 'SST-II',
                'name'               => 'Science and Technology Teacher II',
                'salary_grade'       => 13,
                'full_load_threshold'=> 18.00,
                'min_teaching_units' => 9.00,
                'max_admin_units'    => 15.00,
                'max_research_units' => 5.00,
                'overload_threshold' => 18.00,
                'max_overload_units' => 6.00,
                'description'        => 'Mid-entry SST. Begins to take on committee and minor research advisory roles.',
            ],
            [
                'code'               => 'SST-III',
                'name'               => 'Science and Technology Teacher III',
                'salary_grade'       => 14,
                'full_load_threshold'=> 18.00,
                'min_teaching_units' => 6.00,   // mid-level: balanced teaching + non-teaching
                'max_admin_units'    => 15.00,
                'max_research_units' => 5.00,
                'overload_threshold' => 18.00,
                'max_overload_units' => 6.00,
                'description'        => 'Mid-level SST. Expected to be active in research advisory, '
                                      . 'committee work, and departmental administrative duties.',
            ],
            [
                'code'               => 'SST-IV',
                'name'               => 'Science and Technology Teacher IV',
                'salary_grade'       => 15,
                'full_load_threshold'=> 18.00,
                'min_teaching_units' => 6.00,
                'max_admin_units'    => 15.00,
                'max_research_units' => 5.00,
                'overload_threshold' => 18.00,
                'max_overload_units' => 6.00,
                'description'        => 'Senior SST. Expected to lead committees and research programs, '
                                      . 'mentor junior faculty, and assume significant administrative roles.',
            ],
            [
                'code'               => 'SST-V',
                'name'               => 'Science and Technology Teacher V',
                'salary_grade'       => 17,
                'full_load_threshold'=> 18.00,
                'min_teaching_units' => 6.00,
                'max_admin_units'    => 15.00,
                'max_research_units' => 5.00,
                'overload_threshold' => 18.00,
                'max_overload_units' => 6.00,
                'description'        => 'Master-level SST. Top-tier faculty expected to lead institutional '
                                      . 'research, chair key committees, and provide academic leadership.',
            ],
        ];

        foreach ($positions as $position) {
            DB::table('sst_positions')->updateOrInsert(
                ['code' => $position['code']],
                array_merge($position, [
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $this->command->info('SST Positions seeded (SST-I through SST-V).');
    }
}
