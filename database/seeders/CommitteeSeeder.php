<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommitteeSeeder extends Seeder
{
    /**
     * Seed standard institutional committees at a PSHS campus.
     *
     * Per BOT Resolution No. 2025-05-80, every faculty must hold
     * membership in at least one committee per academic term.
     * Chairpersonships carry higher load credit (1.00 unit default).
     */
    public function run(): void
    {
        $now = now();

        $committees = [
            // ── Academic Committees ───────────────────────────────────────────
            [
                'name'                   => 'Academic Committee',
                'code'                   => 'ACAD',
                'committee_type'         => 'academic',
                'description'            => 'Oversees curriculum delivery, academic standards, and instructional quality.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Curriculum and Instruction Committee',
                'code'                   => 'CIC',
                'committee_type'         => 'academic',
                'description'            => 'Reviews and develops the academic curriculum aligned with PSHSS standards.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Faculty Development Committee',
                'code'                   => 'FDC',
                'committee_type'         => 'academic',
                'description'            => 'Plans and coordinates professional development programs for academic staff.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],

            // ── Research Committees ───────────────────────────────────────────
            [
                'name'                   => 'Research and Development Committee',
                'code'                   => 'RDEV',
                'committee_type'         => 'research',
                'description'            => 'Promotes faculty and student research, manages research grants and dissemination.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Science Fair and Competitions Committee',
                'code'                   => 'SFCC',
                'committee_type'         => 'research',
                'description'            => 'Organizes the campus science fair and coordinates participation in external competitions.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],

            // ── Student Affairs Committees ────────────────────────────────────
            [
                'name'                   => 'Student Affairs Committee',
                'code'                   => 'SAC',
                'committee_type'         => 'student_affairs',
                'description'            => 'Manages student welfare, activities, and co-curricular programs.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Guidance and Counseling Committee',
                'code'                   => 'GCC',
                'committee_type'         => 'student_affairs',
                'description'            => 'Supports student mental health, career guidance, and socio-emotional well-being.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Scholarship and Financial Assistance Committee',
                'code'                   => 'SFAC',
                'committee_type'         => 'student_affairs',
                'description'            => 'Administers scholarship programs and financial assistance for qualified scholars.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],

            // ── Co-curricular / Cultural Committees ───────────────────────────
            [
                'name'                   => 'Cultural and Arts Committee',
                'code'                   => 'CAC',
                'committee_type'         => 'co_curricular',
                'description'            => 'Promotes cultural awareness and supports arts-related activities and performances.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Athletics and Sports Committee',
                'code'                   => 'ASC',
                'committee_type'         => 'co_curricular',
                'description'            => 'Manages intramural and inter-school athletic activities and sports programs.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Special Programs Committee',
                'code'                   => 'SPC',
                'committee_type'         => 'co_curricular',
                'description'            => 'Coordinates special campus programs including immersion, outreach, and advocacy events.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],

            // ── Administrative Committees ─────────────────────────────────────
            [
                'name'                   => 'Administrative Committee',
                'code'                   => 'ADMIN',
                'committee_type'         => 'administrative',
                'description'            => 'Handles general administrative concerns, policies, and campus operations.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'ICT and Technology Committee',
                'code'                   => 'ICTC',
                'committee_type'         => 'administrative',
                'description'            => 'Manages ICT infrastructure, digital learning resources, and technology policies.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Property and Procurement Committee',
                'code'                   => 'PPC',
                'committee_type'         => 'administrative',
                'description'            => 'Oversees campus procurement, inventory, and property management.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Alumni Relations Committee',
                'code'                   => 'ARC',
                'committee_type'         => 'administrative',
                'description'            => 'Maintains engagement with alumni and coordinates alumni-related programs.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],

            // ── Discipline Committees ─────────────────────────────────────────
            [
                'name'                   => 'Discipline Committee',
                'code'                   => 'DISC',
                'committee_type'         => 'discipline',
                'description'            => 'Addresses student disciplinary cases and enforces the Student Handbook.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],
            [
                'name'                   => 'Honor and Awards Committee',
                'code'                   => 'HAC',
                'committee_type'         => 'discipline',
                'description'            => 'Reviews and recommends faculty and student awards, recognitions, and honors.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],

            // ── Health & Safety ───────────────────────────────────────────────
            [
                'name'                   => 'Health and Safety Committee',
                'code'                   => 'HSC',
                'committee_type'         => 'health_and_safety',
                'description'            => 'Ensures campus health protocols, safety standards, and emergency preparedness.',
                'chairperson_load_units' => 1.00,
                'member_load_units'      => 0.50,
            ],

            // ── Finance ───────────────────────────────────────────────────────
            [
                'name'                   => 'Budget and Finance Committee',
                'code'                   => 'BFC',
                'committee_type'         => 'finance',
                'description'            => 'Reviews budget proposals, monitors expenditures, and oversees fiscal compliance.',
                'chairperson_load_units' => 1.50,
                'member_load_units'      => 0.50,
            ],
        ];

        foreach ($committees as $committee) {
            DB::table('committees')->updateOrInsert(
                ['code' => $committee['code']],
                array_merge($committee, [
                    'max_members'       => null,
                    'chairperson_title' => 'Chairperson',
                    'is_active'         => true,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ])
            );
        }

        $this->command->info('Committees seeded (' . count($committees) . ' committees).');
    }
}
