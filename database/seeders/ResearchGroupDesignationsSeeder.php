<?php

namespace Database\Seeders;

use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the specific Research Advisory group designations from the
 * Faculty Loading 2025-2026 PDF.
 *
 * Replaces the three generic placeholders (RES-G10, RES-G11, RES-G12) with
 * the actual numbered groups. G7/G8/G9 placeholders are left untouched.
 *
 * All groups:
 *   - requires_unit = true  (unit-scoped)
 *   - max_holders   = 1
 *   - load_units    = as per PDF (1 for G10, 2 for paired G11/G12 groups, 1 for single-member groups)
 */
class ResearchGroupDesignationsSeeder extends Seeder
{
    public function run(): void
    {
        $cat = DesignationCategory::where('code', 'RESEARCH')->firstOrFail();

        // Remove the three generic placeholders that these groups replace
        Designation::whereIn('code', ['RES-G10', 'RES-G11', 'RES-G12'])->delete();

        $sort = 10; // start after G7(1) G8(2) G9(3) placeholders, leave gap

        // ── Grade 10 Groups (load = 1 unit each) ─────────────────────────────

        $g10 = [
            'G10 GROUP 1-2',  'G10 GROUP 3-4',  'G10 GROUP 5-6',  'G10 GROUP 7-8',
            'G10 GROUP 9-10', 'G10 GROUP 11-12','G10 GROUP 13-14','G10 GROUP 15-16',
            'G10 GROUP 17-18','G10 GROUP 19-20','G10 GROUP 21-22','G10 GROUP 23-24',
            'G10 GROUP 25-26','G10 GROUP 27-28','G10 GROUP 29-30','G10 GROUP 31-32',
            'G10 GROUP 33-34','G10 GROUP 35-36','G10 GROUP 37-38','G10 GROUP 39-40',
        ];

        foreach ($g10 as $name) {
            $tag = str_replace([' ', '-'], ['-', '-'], strtoupper($name)); // RES-G10-GROUP-1-2
            $this->make($cat->id, "RES-{$tag}", "Research — {$name}", 1, $sort++);
        }

        // ── Grade 11 Research A Groups ────────────────────────────────────────
        // Paired groups = 2 units; single-member groups = 1 unit

        $g11 = [
            ['G11 RES-A GROUP 1-2',  2], ['G11 RES-A GROUP 3-4',  2],
            ['G11 RES-A GROUP 5-6',  2], ['G11 RES-A GROUP 7-8',  2],
            ['G11 RES-A GROUP 9-10', 2], ['G11 RES-A GROUP 11-12',2],
            ['G11 RES-A GROUP 13-14',2], ['G11 RES-A GROUP 15-16',2],
            ['G11 RES-A GROUP 17-18',2], ['G11 RES-A GROUP 19-20',2],
            ['G11 RES-A GROUP 21',   1], ['G11 RES-A GROUP 22',   1],
            ['G11 RES-A GROUP 23-24',2], ['G11 RES-A GROUP 25-26',2],
            ['G11 RES-A GROUP 27-28',2],
            ['G11 RES-A GROUP 29',   1], ['G11 RES-A GROUP 30',   1],
        ];

        foreach ($g11 as [$name, $units]) {
            $tag = str_replace([' ', '-'], ['-', '-'], strtoupper($name));
            $this->make($cat->id, "RES-{$tag}", "Research — {$name}", $units, $sort++);
        }

        // ── Grade 12 Research B Groups (all paired, 2 units each) ────────────

        $g12 = [
            'G12 RES-B GROUP 1-2',  'G12 RES-B GROUP 3-4',  'G12 RES-B GROUP 5-6',
            'G12 RES-B GROUP 7-8',  'G12 RES-B GROUP 9-10', 'G12 RES-B GROUP 11-12',
            'G12 RES-B GROUP 13-14','G12 RES-B GROUP 15-16','G12 RES-B GROUP 17-18',
            'G12 RES-B GROUP 19-20','G12 RES-B GROUP 21-22','G12 RES-B GROUP 23-24',
            'G12 RES-B GROUP 25-26','G12 RES-B GROUP 27-28','G12 RES-B GROUP 29-30',
        ];

        foreach ($g12 as $name) {
            $tag = str_replace([' ', '-'], ['-', '-'], strtoupper($name));
            $this->make($cat->id, "RES-{$tag}", "Research — {$name}", 2, $sort++);
        }

        $this->command->info("Research group designations seeded: " . (($sort - 10)) . " entries.");
    }

    private function make(int $catId, string $code, string $name, int $units, int $sort): void
    {
        Designation::firstOrCreate(['code' => $code], [
            'designation_category_id' => $catId,
            'name'          => $name,
            'load_units'    => $units,
            'requires_unit' => true,
            'max_holders'   => 1,
            'sort_order'    => $sort,
            'is_active'     => true,
        ]);
    }
}
