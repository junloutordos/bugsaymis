<?php

namespace Database\Seeders;

use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 22 student sections for the current FL school year
 * (from the Faculty Loading 2025-2026 PDF).
 *
 * Uses firstOrCreate on (levelid, sectionname, school_year_id) so it
 * is safe to re-run. Does NOT touch sections from other school years.
 */
class CurrentYearSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $sy = SchoolYear::where('is_current', true)->firstOrFail();

        $sections = [
            // Grade 7
            [7,  'Aquamarine'],
            [7,  'Opal'],
            [7,  'Turquoise'],
            [7,  'Sapphire'],
            // Grade 8
            [8,  'Anthurium'],
            [8,  'Carnation'],
            [8,  'Sunflower'],
            [8,  'Daffodil'],
            // Grade 9
            [9,  'Calcium'],
            [9,  'Lithium'],
            [9,  'Sodium'],
            [9,  'Barium'],
            // Grade 10
            [10, 'Electron'],
            [10, 'Graviton'],
            [10, 'Proton'],
            [10, 'Neutron'],
            // Grade 11
            [11, 'Mars'],
            [11, 'Mercury'],
            [11, 'Venus'],
            // Grade 12
            [12, 'Del Mundo'],
            [12, 'Orosa'],
            [12, 'Zara'],
        ];

        $created = 0;

        foreach ($sections as [$grade, $name]) {
            $exists = DB::table('sections')
                ->where('levelid', $grade)
                ->where('sectionname', $name)
                ->where('school_year_id', $sy->id)
                ->exists();

            if (! $exists) {
                DB::table('sections')->insert([
                    'levelid'        => $grade,
                    'sectionname'    => $name,
                    'section_code'   => 'G' . $grade . '-' . strtoupper(str_replace(' ', '-', $name)),
                    'strand'         => 'STEM',
                    'school_year_id' => $sy->id,
                    'syid'           => $sy->id,
                    'is_active'      => 1,
                    'capacity'       => 30,
                ]);
                $created++;
            }
        }

        $this->command->info("Sections seeded for {$sy->name}: {$created} created (skipped " . (count($sections) - $created) . " existing).");
    }
}
