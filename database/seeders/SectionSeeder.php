<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionSeeder extends Seeder
{
    /**
     * Seed student sections for the current school year.
     *
     * Maps to the legacy sections table schema:
     *   syid        — school year ID
     *   levelid     — grade level (7–12)
     *   sectionname — section name (e.g., Newton)
     *   section_code— short code added by FL migration 000011
     *   capacity    — max students (added by FL migration 000011)
     *   is_active   — soft-disable flag (added by FL migration 000011)
     *
     * PSHS-CRC sections are named after notable scientists.
     * Five sections per grade level (typical for ~270 scholars/batch).
     */
    public function run(): void
    {
        $now = now()->toDateTimeString();

        // Resolve the current school year
        $schoolYear = DB::table('school_years')
            ->where('is_current', true)
            ->first()
            ?? DB::table('school_years')->orderBy('id')->first();

        if (! $schoolYear) {
            $this->command->warn('No school year found — skipping SectionSeeder. Run SchoolYearSeeder first.');
            return;
        }

        $syId = $schoolYear->id;

        $sectionsByGrade = [
            7  => ['Aquamarine', 'Opal',     'Turquoise', 'Sapphire'],
            8  => ['Anthurium',  'Carnation','Sunflower', 'Daffodil'],
            9  => ['Calcium',    'Lithium',  'Sodium',    'Barium'],
            10 => ['Electron',   'Graviton', 'Proton',    'Neutron'],
            11 => ['Mars',       'Mercury',  'Venus',     'Del Mundo'],
            12 => ['Orosa',      'Zara'],
        ];

        $inserted = 0;

        foreach ($sectionsByGrade as $grade => $names) {
            foreach ($names as $name) {
                $code = 'G' . $grade . '-' . strtoupper(str_replace(' ', '_', $name));

                DB::table('sections')->updateOrInsert(
                    [
                        'syid'        => $syId,
                        'levelid'     => $grade,
                        'sectionname' => $name,
                    ],
                    [
                        'school_year_id' => $syId,
                        'section_code'   => $code,
                        'capacity'       => 30,
                        'adviser'        => null,
                        'is_active'      => true,
                    ]
                );
                $inserted++;
            }
        }

        $this->command->info("Sections seeded ({$inserted} sections for S.Y. {$schoolYear->name}).");
    }
}
