<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\Section;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Illuminate\Console\Command;

class SyncSectionDesignations extends Command
{
    protected $signature = 'faculty-loading:sync-section-designations
                            {--school-year-id= : Limit to sections of a specific school year}';

    protected $description = 'Create HRA-/HAC- designations for all sections (grades 7–12) that do not have one yet.';

    public function handle(HeadAdvisoryService $advisory): int
    {
        $query = Section::query()
            ->whereBetween('levelid', [7, 12])
            ->whereNotNull('school_year_id');

        if ($syId = $this->option('school-year-id')) {
            $query->where('school_year_id', $syId);
        }

        $sections = $query->orderBy('school_year_id')->orderBy('levelid')->orderBy('id')->get();

        if ($sections->isEmpty()) {
            $this->info('No sections found.');
            return self::SUCCESS;
        }

        $this->info("Processing {$sections->count()} sections…");

        $bar     = $this->output->createProgressBar($sections->count());
        $created = 0;
        $found   = 0;

        foreach ($sections as $section) {
            $designation = $advisory->ensureSectionDesignation($section);

            if ($designation && $designation->wasRecentlyCreated) {
                $created++;
                $this->line("\n  <info>Created</info> {$designation->code} — {$designation->name}");
            } else {
                $found++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Created: {$created} | Already existed: {$found}");

        return self::SUCCESS;
    }
}
