<?php

namespace App\Jobs;

use App\Services\ComputerLabSchedulingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncComputerLabBookings implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function __construct(public int $academicTermId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'computer-lab-term-'.$this->academicTermId;
    }

    public function handle(ComputerLabSchedulingService $scheduler): void
    {
        $scheduler->synchronizeTerm($this->academicTermId);
    }
}
