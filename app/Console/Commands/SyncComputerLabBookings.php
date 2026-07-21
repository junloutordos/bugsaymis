<?php

namespace App\Console\Commands;

use App\Models\FacultyLoading\AcademicTerm;
use App\Services\ComputerLabSchedulingService;
use Illuminate\Console\Command;

class SyncComputerLabBookings extends Command
{
    protected $signature = 'computer-labs:sync {term? : Academic term ID}';

    protected $description = 'Synchronize priority computer subjects into the separate computer laboratory calendar';

    public function handle(ComputerLabSchedulingService $scheduler): int
    {
        $termId = $this->argument('term')
            ? (int) $this->argument('term')
            : (int) AcademicTerm::where('is_current', true)->value('id');

        if (! $termId) {
            $this->warn('No academic term was supplied and no current term exists.');

            return self::FAILURE;
        }

        $result = $scheduler->synchronizeTerm($termId);
        $this->info("Synchronized {$result['confirmed']} confirmed reservation(s); {$result['unassigned']} unassigned; {$result['displaced']} ad-hoc request(s) returned to pending.");

        return self::SUCCESS;
    }
}
