<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollItem;
use App\Models\User;
use App\Services\Payroll\PayrollPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PayrollEmployeeController extends Controller
{
    public function __construct(private PayrollPdfService $pdf) {}

    public function index()
    {
        $userId = Auth::id();

        $payslips = PayrollItem::with('batch:id,payroll_no,period_start,period_end,month,year')
            ->where('matched_user_id', $userId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(24);

        return Inertia::render('Payroll/Employee/Index', [
            'payslips' => $payslips,
        ]);
    }

    public function show(PayrollItem $payrollItem)
    {
        // Strictly scope to own record — never accept user_id param
        abort_if($payrollItem->matched_user_id !== Auth::id(), 403);

        $payrollItem->load(['batch', 'employee:id,name,position,employee_no']);

        return Inertia::render('Payroll/Employee/Show', [
            'item'  => $payrollItem,
            'batch' => $payrollItem->batch,
        ]);
    }

    public function pdf(PayrollItem $payrollItem)
    {
        abort_if($payrollItem->matched_user_id !== Auth::id(), 403);

        $payrollItem->load(['batch', 'employee:id,name,position,employee_no,division_id,office']);

        return $this->pdf->stream($payrollItem);
    }
}
