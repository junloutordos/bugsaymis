<?php

namespace App\Jobs\Payroll;

use App\Models\Payroll\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Master job: dispatches one ComputeEmployeePayroll job per active employee
 * and then chains UpdatePayrollRunTotals on completion.
 *
 * ShouldBeUnique ensures only one instance per payroll run can be queued at a time,
 * preventing double-processing if the "Process" button is clicked more than once.
 */
class ProcessPayrollRun implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout     = 60;
    public int $tries       = 2;
    public int $uniqueFor   = 300; // lock for 5 minutes

    public function __construct(public readonly int $payrollRunId) {}

    public function uniqueId(): string
    {
        return (string) $this->payrollRunId;
    }

    public function handle(): void
    {
        $run = PayrollRun::findOrFail($this->payrollRunId);

        $run->update(['status' => 'processing']);

        $users = User::where('status', 'active')
            ->whereHas('employeeProfile')
            ->get();

        $jobs = $users->map(
            fn ($u) => new ComputeEmployeePayroll($run->id, $u->id)
        )->all();

        Bus::batch($jobs)
            ->name("Payroll Run #{$run->id} — {$run->year}-{$run->month} {$run->period}")
            ->finally(function () use ($run) {
                // After all employees computed, update totals and mark for_review
                UpdatePayrollRunTotals::dispatch($run->id);
            })
            ->dispatch();

        Log::info("PayrollRun #{$run->id} batch dispatched", ['employees' => count($jobs)]);
    }

    public function failed(\Throwable $e): void
    {
        PayrollRun::where('id', $this->payrollRunId)->update(['status' => 'draft']);

        Log::error("ProcessPayrollRun failed", [
            'run_id' => $this->payrollRunId,
            'error'  => $e->getMessage(),
        ]);
    }
}
