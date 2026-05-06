<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StanineLookupSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('stanine_lookup')->delete();

        $rows = [
            [100, 1.000, 'Excellent'],
            [99,  1.027, 'Excellent'],
            [98,  1.053, 'Excellent'],
            [97,  1.079, 'Excellent'],
            [96,  1.105, 'Excellent'],
            [95,  1.140, 'Very Good'],
            [94,  1.190, 'Very Good'],
            [93,  1.230, 'Very Good'],
            [92,  1.270, 'Very Good'],
            [91,  1.310, 'Very Good'],
            [90,  1.350, 'Very Good'],
            [89,  1.390, 'Very Good'],
            [88,  1.440, 'Very Good'],
            [87,  1.480, 'Very Good'],
            [86,  1.520, 'Very Good'],
            [85,  1.560, 'Very Good'],
            [84,  1.600, 'Very Good'],
            [83,  1.640, 'Good'],
            [82,  1.690, 'Good'],
            [81,  1.730, 'Good'],
            [80,  1.770, 'Good'],
            [79,  1.810, 'Good'],
            [78,  1.850, 'Good'],
            [77,  1.890, 'Good'],
            [76,  1.940, 'Good'],
            [75,  1.980, 'Good'],
            [74,  2.020, 'Good'],
            [73,  2.060, 'Good'],
            [72,  2.100, 'Good'],
            [71,  2.140, 'Satisfactory'],
            [70,  2.190, 'Satisfactory'],
            [69,  2.230, 'Satisfactory'],
            [68,  2.270, 'Satisfactory'],
            [67,  2.310, 'Satisfactory'],
            [66,  2.350, 'Satisfactory'],
            [65,  2.390, 'Satisfactory'],
            [64,  2.440, 'Satisfactory'],
            [63,  2.480, 'Satisfactory'],
            [62,  2.520, 'Satisfactory'],
            [61,  2.560, 'Satisfactory'],
            [60,  2.600, 'Satisfactory'],
            [59,  2.640, 'Fair'],
            [58,  2.689, 'Fair'],
            [57,  2.737, 'Fair'],
            [56,  2.785, 'Fair'],
            [55,  2.833, 'Fair'],
            [54,  2.890, 'Fair'],
            [53,  3.013, 'Fair'],
            [52,  3.135, 'Fair'],
            [51,  3.257, 'Fair'],
            [50,  3.379, 'Fair'],
            [49,  3.510, 'Failed on Condition'],
            [48,  3.610, 'Failed on Condition'],
            [47,  3.709, 'Failed on Condition'],
            [46,  3.808, 'Failed on Condition'],
            [45,  3.907, 'Failed on Condition'],
            [44,  4.006, 'Failed on Condition'],
            [43,  4.105, 'Failed on Condition'],
            [42,  4.204, 'Failed on Condition'],
            [41,  4.303, 'Failed on Condition'],
            [40,  4.402, 'Failed on Condition'],
            [39,  4.510, 'Failed'],
        ];

        $now = now();
        $insert = array_map(fn ($r) => [
            'percentage'           => $r[0],
            'grade_equivalent'     => $r[1],
            'adjectival_equivalent'=> $r[2],
            'created_at'           => $now,
            'updated_at'           => $now,
        ], $rows);

        DB::table('stanine_lookup')->insert($insert);

        $this->command->info('StanineLookupSeeder: seeded ' . count($insert) . ' stanine rows.');
    }
}
