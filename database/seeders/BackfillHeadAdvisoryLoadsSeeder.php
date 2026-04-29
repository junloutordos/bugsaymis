<?php

namespace Database\Seeders;

use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\Section;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Illuminate\Database\Seeder;

/**
 * One-time backfill: creates load assignments for all academic unit heads
 * and section advisers that were assigned before HeadAdvisoryService was wired up.
 */
class BackfillHeadAdvisoryLoadsSeeder extends Seeder
{
    public function __construct(private HeadAdvisoryService $advisory) {}

    public function run(): void
    {
        // ── Academic Unit Heads ───────────────────────────────────────────────
        $units = AcademicUnit::whereNotNull('head_user_id')->get();

        $unitCount = 0;
        foreach ($units as $unit) {
            $this->advisory->syncUnitHead($unit, null);
            $this->command->line("  Unit head: {$unit->code} → user {$unit->head_user_id}");
            $unitCount++;
        }

        $this->command->info("Unit heads backfilled: {$unitCount}");

        // ── Section Advisers ─────────────────────────────────────────────────
        $sections = Section::whereNotNull('adviser')
            ->whereNotNull('school_year_id')
            ->get();

        $sectionCount = 0;
        foreach ($sections as $section) {
            $this->advisory->syncSectionAdviser($section, null);
            $this->command->line("  Section adviser: {$section->sectionname} (G{$section->levelid}) → user {$section->adviser}");
            $sectionCount++;
        }

        $this->command->info("Section advisers backfilled: {$sectionCount}");
    }
}
