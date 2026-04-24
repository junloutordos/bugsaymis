<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds PSHS-CRC classrooms:
 *  - 22 section homerooms (one per section, matching section names)
 *  - 4 Computer Laboratories
 *  - 1 Fabrication Laboratory
 *  - 1 Chemistry Laboratory
 *  - 1 Biology Laboratory
 *  - 1 Physics Laboratory
 *
 * Truncates and re-seeds on every run.
 */
class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('classrooms')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $now = now();

        // ── Section homerooms ─────────────────────────────────────────────────
        // One room per section (named after the section). Capacity 30.

        $sections = [
            // G7
            ['Aquamarine', 'RM-AQUAMARINE'],
            ['Opal',       'RM-OPAL'],
            ['Turquoise',  'RM-TURQUOISE'],
            ['Sapphire',   'RM-SAPPHIRE'],
            // G8
            ['Anthurium',  'RM-ANTHURIUM'],
            ['Carnation',  'RM-CARNATION'],
            ['Sunflower',  'RM-SUNFLOWER'],
            ['Daffodil',   'RM-DAFFODIL'],
            // G9
            ['Calcium',    'RM-CALCIUM'],
            ['Lithium',    'RM-LITHIUM'],
            ['Sodium',     'RM-SODIUM'],
            ['Barium',     'RM-BARIUM'],
            // G10
            ['Electron',   'RM-ELECTRON'],
            ['Graviton',   'RM-GRAVITON'],
            ['Proton',     'RM-PROTON'],
            ['Neutron',    'RM-NEUTRON'],
            // G11
            ['Mars',       'RM-MARS'],
            ['Mercury',    'RM-MERCURY'],
            ['Venus',      'RM-VENUS'],
            // G12
            ['Del Mundo',  'RM-DEL-MUNDO'],
            ['Orosa',      'RM-OROSA'],
            ['Zara',       'RM-ZARA'],
        ];

        $rows = [];

        foreach ($sections as [$name, $code]) {
            $rows[] = [
                'name'           => "Room {$name}",
                'code'           => $code,
                'classroom_type' => 'lecture',
                'capacity'       => 30,
                'is_available'   => true,
                'room_id'        => null,
                'remarks'        => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // ── Computer Laboratories (4) ─────────────────────────────────────────

        for ($i = 1; $i <= 4; $i++) {
            $rows[] = [
                'name'           => "Computer Laboratory {$i}",
                'code'           => "COMPLAB-{$i}",
                'classroom_type' => 'ict_lab',
                'capacity'       => 30,
                'is_available'   => true,
                'room_id'        => null,
                'remarks'        => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // ── Specialized & Science Laboratories ───────────────────────────────

        $labs = [
            ['Fabrication Laboratory', 'FABLAB',  'specialized'],
            ['Chemistry Laboratory',   'CHEMLAB', 'chemistry_lab'],
            ['Biology Laboratory',     'BIOLAB',  'biology_lab'],
            ['Physics Laboratory',     'PHYSLAB', 'physics_lab'],
        ];

        foreach ($labs as [$name, $code, $type]) {
            $rows[] = [
                'name'           => $name,
                'code'           => $code,
                'classroom_type' => $type,
                'capacity'       => 30,
                'is_available'   => true,
                'room_id'        => null,
                'remarks'        => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        DB::table('classrooms')->insert($rows);

        $this->command->info('Classrooms seeded: ' . count($rows) . ' rooms (' . count($sections) . ' homerooms + 4 computer labs + 4 science/specialized labs).');
    }
}
