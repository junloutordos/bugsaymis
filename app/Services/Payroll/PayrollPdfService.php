<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollItem;
use App\Models\User;
use Illuminate\Http\Response;
use Mpdf\Mpdf;

class PayrollPdfService
{
    public function generate(PayrollItem $item): string
    {
        [$preparedBy, $certifiedBy] = $this->signatories();

        $html = view('payroll.payslip', [
            'item'        => $item,
            'batch'       => $item->batch,
            'preparedBy'  => $preparedBy,
            'certifiedBy' => $certifiedBy,
        ])->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 10,
            'margin_bottom' => 10,
            'margin_left'   => 12,
            'margin_right'  => 12,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    public function stream(PayrollItem $item): Response
    {
        $bytes    = $this->generate($item);
        $filename = $this->filename($item);

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function filename(PayrollItem $item): string
    {
        $name  = str_replace(['/', '\\', ' '], ['', '', '_'], $item->employee_name_raw ?? 'Employee');
        $start = \Carbon\Carbon::parse($item->batch->period_start)->format('Y-m-d');
        $end   = \Carbon\Carbon::parse($item->batch->period_end)->format('Y-m-d');
        return "Payslip-{$name}-{$start}-{$end}.pdf";
    }

    /**
     * Fetch the two signatories:
     * - Prepared by: HR role + Plantilla Non-Teaching emp_category
     * - Certified Correct: position LIKE '%Accountant II%'
     */
    public function signatories(): array
    {
        $preparedBy = User::whereHas('roles', fn($q) => $q->where('name', 'HR'))
            ->where('emp_category', 'Plantilla Non-Teaching')
            ->where('status', '<>', 'inactive')
            ->first();

        $certifiedBy = User::where('position', 'like', '%Accountant II%')
            ->where('status', '<>', 'inactive')
            ->first();

        return [$preparedBy, $certifiedBy];
    }
}
