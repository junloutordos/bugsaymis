<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed the 3rd Tranche Monthly Salary Schedule.
 *
 * Source: Annex A — The Third Tranche Monthly Salary Schedule
 *         for the Civilian Personnel of the National Government
 *         Effective January 1, 2026 (In Pesos)
 *
 * Monthly rates for Salary Grades 1–33, Steps 1–8.
 * SG 33 only has Steps 1–2 per the official schedule.
 *
 * Running this seeder will:
 *   1. Mark all previous salary schedule rows as is_current = false
 *   2. Insert/upsert the 2026 3rd Tranche rows as is_current = true
 */
class SalarySchedule2026Seeder extends Seeder
{
    public function run(): void
    {
        $scheduleName  = '3rd Tranche 2026 (Effective Jan 1, 2026)';
        $effectiveDate = '2026-01-01';
        $now           = now();

        // Monthly rates per SG (1–33), Steps 1–8
        // Source: Official Annex A — 3rd Tranche 2026
        $schedule = [
             1 => [14_634, 14_730, 14_849, 14_968, 15_089, 15_211, 15_333, 15_456],
             2 => [15_522, 15_636, 15_752, 15_869, 15_986, 16_103, 16_223, 16_342],
             3 => [16_486, 16_610, 16_732, 16_856, 16_982, 17_106, 17_234, 17_360],
             4 => [17_506, 17_636, 17_767, 17_898, 18_031, 18_163, 18_298, 18_433],
             5 => [18_581, 18_720, 18_858, 18_998, 19_137, 19_280, 19_423, 19_565],
             6 => [19_716, 19_862, 20_009, 20_158, 20_307, 20_456, 20_609, 20_761],
             7 => [20_914, 21_069, 21_224, 21_382, 21_539, 21_699, 21_859, 22_022],
             8 => [22_423, 22_627, 22_832, 23_038, 23_246, 23_456, 23_668, 23_883],
             9 => [24_329, 24_523, 24_720, 24_917, 25_117, 25_318, 25_521, 25_725],
            10 => [26_917, 27_131, 27_347, 27_565, 27_786, 28_007, 28_230, 28_456],
            11 => [31_705, 31_820, 32_109, 32_401, 32_697, 32_998, 33_302, 33_611],
            12 => [33_947, 34_069, 34_357, 34_648, 34_943, 35_242, 35_544, 35_850],
            13 => [36_125, 36_283, 36_599, 36_919, 37_244, 37_572, 37_904, 38_241],
            14 => [38_764, 39_141, 39_523, 39_910, 40_300, 40_696, 41_097, 41_503],
            15 => [42_178, 42_594, 43_015, 43_442, 43_874, 44_310, 44_753, 45_202],
            16 => [45_694, 46_152, 46_615, 47_084, 47_559, 48_040, 48_528, 49_020],
            17 => [49_562, 50_066, 50_576, 51_092, 51_614, 52_144, 52_678, 53_221],
            18 => [53_818, 54_371, 54_933, 55_499, 56_075, 56_657, 57_246, 57_842],
            19 => [59_153, 59_966, 60_793, 61_632, 62_486, 63_353, 64_236, 65_132],
            20 => [66_052, 66_970, 67_904, 68_853, 69_818, 70_772, 71_727, 72_671],
            21 => [73_303, 74_337, 75_388, 76_456, 77_542, 78_645, 79_692, 80_831],
            22 => [81_796, 82_963, 84_151, 85_356, 86_582, 87_746, 89_011, 90_295],
            23 => [91_306, 92_622, 93_962, 95_330, 96_823, 98_341, 99_883, 101_318],
            24 => [102_603, 104_209, 105_841, 107_500, 109_185, 110_898, 112_533, 114_301],
            25 => [116_643, 118_469, 120_326, 122_212, 124_131, 126_079, 128_061, 130_073],
            26 => [131_807, 133_870, 135_968, 138_100, 140_268, 142_469, 144_707, 146_983],
            27 => [148_940, 151_273, 153_644, 155_906, 158_353, 160_235, 162_752, 165_310],
            28 => [167_129, 169_752, 172_418, 174_797, 177_545, 180_339, 182_660, 185_537],
            29 => [187_531, 190_482, 193_480, 196_528, 199_624, 202_005, 205_191, 208_430],
            30 => [210_718, 214_038, 217_207, 220_425, 223_691, 227_224, 230_595, 234_240],
            31 => [300_961, 306_691, 312_532, 318_182, 323_938, 329_989, 336_092, 342_310],
            32 => [356_237, 363_257, 370_418, 377_359, 384_805, 392_400, 400_150, 408_055],
            33 => [449_157, 462_329], // SG 33 has only Steps 1–2 per official schedule
        ];

        DB::transaction(function () use ($schedule, $scheduleName, $effectiveDate, $now) {
            // Deactivate all previous schedules
            DB::table('salary_schedules')->update(['is_current' => false]);

            $rows = [];
            foreach ($schedule as $sg => $steps) {
                foreach ($steps as $stepIndex => $monthlyRate) {
                    $rows[] = [
                        'salary_grade'   => $sg,
                        'step'           => $stepIndex + 1,
                        'monthly_rate'   => $monthlyRate,
                        'annual_rate'    => $monthlyRate * 12,
                        'effective_date' => $effectiveDate,
                        'is_current'     => true,
                        'schedule_name'  => $scheduleName,
                        'remarks'        => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            DB::table('salary_schedules')->upsert(
                $rows,
                ['salary_grade', 'step', 'effective_date'],
                ['monthly_rate', 'annual_rate', 'is_current', 'schedule_name', 'updated_at']
            );
        });

        $total = collect($schedule)->sum(fn($steps) => count($steps));
        $this->command->info("Salary Schedule seeded: {$scheduleName} — {$total} rows (SG 1–33).");
    }
}
