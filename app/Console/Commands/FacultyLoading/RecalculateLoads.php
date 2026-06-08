<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Console\Command;

class RecalculateLoads extends Command
{
    protected $signature = 'faculty-loading:recalculate-loads {term? : Academic term ID (omit for all terms)}';
    protected $description = 'Recompute all faculty load totals from load_assignments rows';

    public function handle(LoadComputationService $loads): int
    {
        $termId = $this->argument('term');

        $query = FacultyLoad::query();

        if ($termId) {
            $term = AcademicTerm::find($termId);
            if (! $term) {
                $this->error("Academic term #{$termId} not found.");
                return 1;
            }
            $query->where('academic_term_id', $termId);
            $this->info("Recalculating loads for term: {$term->full_label}");
        } else {
            $this->info('Recalculating loads for ALL terms...');
        }

        $records = $query->get();
        $bar     = $this->output->createProgressBar($records->count());
        $bar->start();

        $updated = 0;
        foreach ($records as $load) {
            $loads->syncLoad($load);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$updated} faculty load record(s) recalculated.");

        return 0;
    }
}
