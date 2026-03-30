<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\PayrollRun;
use App\Services\Payroll\PayslipService;
use App\Services\Payroll\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayslipController extends Controller
{
    public function __construct(
        protected PayslipService $payslips,
        protected ReportService  $reports,
    ) {}

    /**
     * Download a single employee payslip.
     * GET /payroll/{payrollRun}/payslip/{record}
     */
    public function download(PayrollRun $payrollRun, PayrollRecord $payrollRecord)
    {
        $this->authorize('payroll.view');

        // Employees can only download their own payslip
        if (
            ! Auth::user()->can('payroll.process')
            && $payrollRecord->user_id !== Auth::id()
        ) {
            abort(403);
        }

        $pdf      = $this->payslips->generate($payrollRecord);
        $name     = $payrollRecord->user?->name ?? "emp-{$payrollRecord->user_id}";
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $filename = "payslip-{$payrollRun->control_no}-{$safeName}.pdf";

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache',
        ]);
    }

    /**
     * Generate and download all payslips as a ZIP.
     * POST /payroll/{payrollRun}/payslips/zip
     */
    public function downloadAll(PayrollRun $payrollRun)
    {
        $this->authorize('payroll.process');

        $zipPath = $this->payslips->generateAll($payrollRun);
        $filename = "payslips-{$payrollRun->control_no}.zip";

        return response()->download(storage_path("app/{$zipPath}"), $filename)->deleteFileAfterSend();
    }

    /**
     * Download payroll register PDF.
     * GET /payroll/{payrollRun}/reports/register
     */
    public function payrollRegister(PayrollRun $payrollRun)
    {
        $this->authorize('payroll.view');

        $pdf      = $this->reports->payrollRegister($payrollRun);
        $filename = "payroll-register-{$payrollRun->control_no}.pdf";

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download deductions register PDF.
     * GET /payroll/{payrollRun}/reports/deductions
     */
    public function deductionsRegister(PayrollRun $payrollRun)
    {
        $this->authorize('payroll.view');

        $pdf      = $this->reports->deductionsRegister($payrollRun);
        $filename = "deductions-register-{$payrollRun->control_no}.pdf";

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download DTR monthly summary PDF.
     * GET /hr/reports/dtr?year=2026&month=3
     */
    public function dtrSummary(Request $request)
    {
        $this->authorize('hr.dtr.view');

        $data = $request->validate([
            'year'  => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $pdf      = $this->reports->dtrMonthlySummary((int) $data['year'], (int) $data['month']);
        $months   = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $filename = "dtr-summary-{$data['year']}-{$months[$data['month']]}.pdf";

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
