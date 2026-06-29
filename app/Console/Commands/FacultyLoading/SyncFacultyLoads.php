<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Console\Command;

class SyncFacultyLoads extends Command
{
    protected $signature   = 'faculty-loading:sync-loads {--term= : Academic term ID (defaults to current term)}';
    protected $description = 'Re-aggregate load_assignments and update faculty_loads totals for a term';

    public function __construct(private readonly LoadComputationService $loads)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $termId = $this->option('term');

        if (! $termId) {
            $current = AcademicTerm::where('is_current', true)->first();
            if (! $current) {
                $this->error('No current academic term found. Pass --term=ID explicitly.');
                return self::FAILURE;
            }
            $termId = $current->id;
            $this->info("Using current term: {$current->full_label} (id={$termId})");
        }

        $loads = FacultyLoad::where('academic_term_id', $termId)->get();

        if ($loads->isEmpty()) {
            $this->warn("No faculty load records found for term id={$termId}.");
            return self::SUCCESS;
        }

        $this->withProgressBar($loads, function ($fl) {
            $this->loads->syncLoad($fl);
        });

        $this->newLine();
        $this->info("Synced {$loads->count()} faculty load record(s).");

        return self::SUCCESS;
    }
}
