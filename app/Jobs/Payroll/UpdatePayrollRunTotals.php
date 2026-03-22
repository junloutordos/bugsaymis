<?php

namespace App\Jobs\Payroll;

use App\Models\Payroll\PayrollRun;
use App\Services\Payroll\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdatePayrollRunTotals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries   = 3;

    public function __construct(public readonly int $payrollRunId) {}

    public function handle(PayrollService $service): void
    {
        $run = PayrollRun::findOrFail($this->payrollRunId);

        $service->updateRunTotals($run);

        $run->update(['status' => 'for_review']);
    }
}
