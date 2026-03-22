<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class PayslipService
{
    private const AGENCY_NAME    = 'Philippine Science High School - Caraga Region Campus';
    private const AGENCY_ADDRESS = 'Butuan City, Agusan del Norte';
    private const AGENCY_CODE    = 'PSHS-CRC';

    /**
     * Generate a single payslip PDF and return as binary string.
     */
    public function generate(PayrollRecord $record): string
    {
        $record->load([
            'payrollRun',
            'user.employeeProfile.salaryGrade',
            'allowances.allowanceType',
            'deductions.allowanceType',
        ]);

        $html = $this->buildHtml($record);

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A5',
            'orientation'    => 'P',
            'margin_left'    => 12,
            'margin_right'   => 12,
            'margin_top'     => 10,
            'margin_bottom'  => 10,
            'margin_footer'  => 5,
            'default_font'   => 'Arial',
            'default_font_size' => 8,
        ]);

        $mpdf->SetTitle('Payslip — ' . $record->user?->name);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Generate payslips for all records in a run, save to storage, return zip path.
     */
    public function generateAll(PayrollRun $run): string
    {
        $records = $run->payrollRecords()
            ->with(['user', 'allowances.allowanceType', 'deductions.allowanceType'])
            ->get();

        $dir = "payslips/{$run->year}/{$run->month}/{$run->period}";
        Storage::makeDirectory($dir);

        foreach ($records as $record) {
            $pdf      = $this->generate($record);
            $filename = $dir . '/' . $this->safeFilename($record->user?->name ?? "emp-{$record->user_id}") . '.pdf';
            Storage::put($filename, $pdf);
            $record->update(['payslip_path' => $filename]);
        }

        // Create zip archive
        $zipName = "payslips/payslips-{$run->control_no}.zip";
        $zip     = new \ZipArchive();
        $zipPath = storage_path("app/{$zipName}");

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            foreach ($records as $record) {
                if ($record->payslip_path && Storage::exists($record->payslip_path)) {
                    $zip->addFile(
                        Storage::path($record->payslip_path),
                        basename($record->payslip_path)
                    );
                }
            }
            $zip->close();
        }

        return $zipName;
    }

    // ── HTML Template ─────────────────────────────────────────────────────────

    private function buildHtml(PayrollRecord $record): string
    {
        $run      = $record->payrollRun;
        $user     = $record->user;
        $profile  = $user?->employeeProfile;

        $months  = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        $month   = $months[$run->month] ?? $run->month;
        $period  = $run->period === '1st' ? '1st–15th' : '16th–End of Month';

        // Earnings rows
        $earningsRows = '';
        $earningsRows .= $this->row('Basic Pay', $record->basic_pay);

        foreach ($record->allowances as $a) {
            $earningsRows .= $this->row($a->allowanceType?->name ?? 'Allowance', $a->amount);
        }

        // Deductions rows
        $deductionRows = '';
        if ($record->absence_deduction > 0) {
            $deductionRows .= $this->row('Absence Deduction', $record->absence_deduction);
        }
        if ($record->tardiness_deduction > 0) {
            $deductionRows .= $this->row('Tardiness Deduction', $record->tardiness_deduction);
        }
        if ($record->lwop_deduction > 0) {
            $deductionRows .= $this->row('LWOP Deduction', $record->lwop_deduction);
        }

        foreach ($record->deductions as $d) {
            $deductionRows .= $this->row($d->allowanceType?->name ?? 'Deduction', $d->amount);
        }

        if ($record->withheld_tax > 0) {
            $deductionRows .= $this->row('Withholding Tax', $record->withheld_tax);
        }

        $sgStep = $profile ? "SG {$profile->salary_grade_id}-{$profile->step}" : '—';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 8pt; color: #1e293b; margin: 0; }
  .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 6px; margin-bottom: 8px; }
  .agency-name { font-size: 10pt; font-weight: bold; text-transform: uppercase; }
  .agency-sub  { font-size: 7.5pt; color: #475569; }
  .slip-title  { font-size: 9pt; font-weight: bold; margin: 4px 0 2px; letter-spacing: 1px; text-transform: uppercase; }
  .period-info { font-size: 7.5pt; color: #475569; }
  .info-table  { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  .info-table td { padding: 2px 4px; font-size: 7.5pt; }
  .info-table .label { color: #64748b; width: 35%; }
  .info-table .val   { font-weight: bold; }
  .section-title { background: #1e293b; color: #fff; font-weight: bold; font-size: 7.5pt;
                   padding: 3px 6px; margin-bottom: 0; letter-spacing: 0.5px; }
  .items-table   { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  .items-table td { padding: 2px 6px; font-size: 7.5pt; border-bottom: 1px solid #f1f5f9; }
  .items-table td.amount { text-align: right; font-variant-numeric: tabular-nums; }
  .items-table tr.total-row td { font-weight: bold; border-top: 1.5px solid #1e293b; border-bottom: none; font-size: 8pt; }
  .net-box { background: #f8fafc; border: 1.5px solid #1e293b; padding: 6px 10px;
             display: flex; justify-content: space-between; align-items: center;
             margin: 6px 0 10px; }
  .net-label { font-weight: bold; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.5px; }
  .net-amount { font-weight: bold; font-size: 12pt; }
  .attendance-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 3px; margin-bottom: 10px; }
  .att-item  { background: #f8fafc; border: 1px solid #e2e8f0; padding: 3px 5px; text-align: center; }
  .att-label { font-size: 6.5pt; color: #64748b; display: block; }
  .att-val   { font-size: 9pt; font-weight: bold; display: block; }
  .sig-table  { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .sig-table td { width: 50%; padding: 2px 6px; text-align: center; vertical-align: bottom; font-size: 7.5pt; }
  .sig-line   { border-top: 1px solid #1e293b; margin-top: 18px; padding-top: 2px; }
  .sig-label  { color: #64748b; font-size: 6.5pt; }
  .footer-note { text-align: center; font-size: 6pt; color: #94a3b8; margin-top: 8px;
                 border-top: 1px solid #e2e8f0; padding-top: 4px; }
</style>
</head>
<body>

<div class="header">
  <div class="agency-name">{$this->e(self::AGENCY_NAME)}</div>
  <div class="agency-sub">{$this->e(self::AGENCY_ADDRESS)}</div>
  <div class="slip-title">Payslip</div>
  <div class="period-info">{$this->e($month)} {$run->year} &bull; {$this->e($period)} &bull; {$this->e($run->control_no)}</div>
</div>

<table class="info-table">
  <tr><td class="label">Employee Name</td><td class="val">{$this->e($user?->name ?? '—')}</td>
      <td class="label">Position</td><td class="val">{$this->e($user?->position ?? '—')}</td></tr>
  <tr><td class="label">Salary Grade</td><td class="val">{$this->e($sgStep)}</td>
      <td class="label">Monthly Salary</td><td class="val">{$this->php($record->monthly_salary)}</td></tr>
  <tr><td class="label">Department</td><td colspan="3" class="val">{$this->e($user?->division ?? $profile?->division ?? '—')}</td></tr>
</table>

<div class="section-title">Attendance</div>
<div class="attendance-grid" style="margin-top:4px;">
  <div class="att-item"><span class="att-label">Days Worked</span><span class="att-val">{$record->days_worked}</span></div>
  <div class="att-item"><span class="att-label">Days Absent</span><span class="att-val">{$record->days_absent}</span></div>
  <div class="att-item"><span class="att-label">Late (min)</span><span class="att-val">{$record->late_minutes}</span></div>
  <div class="att-item"><span class="att-label">Undertime</span><span class="att-val">{$record->undertime_minutes}</span></div>
</div>

<div class="section-title">Earnings</div>
<table class="items-table">
{$earningsRows}
  <tr class="total-row"><td>Total Gross Pay</td><td class="amount">{$this->php($record->gross_pay)}</td></tr>
</table>

<div class="section-title">Deductions</div>
<table class="items-table">
{$deductionRows}
  <tr class="total-row"><td>Total Deductions</td><td class="amount">{$this->php($record->total_deductions)}</td></tr>
</table>

<table style="width:100%;border-collapse:collapse;background:#f8fafc;border:2px solid #1e293b;">
  <tr>
    <td style="padding:6px 10px;font-weight:bold;font-size:9pt;text-transform:uppercase;letter-spacing:0.5px;">Net Pay</td>
    <td style="padding:6px 10px;text-align:right;font-weight:bold;font-size:13pt;">{$this->php($record->net_pay)}</td>
  </tr>
</table>

<table class="sig-table" style="margin-top:14px;">
  <tr>
    <td>
      <div class="sig-line">{$this->e($user?->name ?? '')}</div>
      <div class="sig-label">Employee / Payee</div>
    </td>
    <td>
      <div class="sig-line">&nbsp;</div>
      <div class="sig-label">Certifying Officer</div>
    </td>
  </tr>
</table>

<div class="footer-note">
  This is a computer-generated payslip. {$this->e(self::AGENCY_CODE)} &bull; {$this->e(now()->format('d M Y H:i'))}
</div>

</body>
</html>
HTML;
    }

    private function row(string $label, float $amount): string
    {
        return "<tr><td>{$this->e($label)}</td><td class=\"amount\">{$this->php($amount)}</td></tr>\n";
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function php(float|int|null $amount): string
    {
        if ($amount === null) return '—';
        return '₱' . number_format((float) $amount, 2);
    }

    private function safeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    }
}
