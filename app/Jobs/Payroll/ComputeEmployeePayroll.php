<?php

namespace App\Jobs\Payroll;

use App\Models\Payroll\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ComputeEmployeePayroll implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 3;

    public function __construct(
        public readonly int $payrollRunId,
        public readonly int $userId,
    ) {}

    public function handle(PayrollService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $run  = PayrollRun::findOrFail($this->payrollRunId);
        $user = User::with('employeeProfile')->findOrFail($this->userId);

        $service->computeForEmployee($run, $user);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ComputeEmployeePayroll failed', [
            'run_id'  => $this->payrollRunId,
            'user_id' => $this->userId,
            'error'   => $e->getMessage(),
        ]);
    }
}
