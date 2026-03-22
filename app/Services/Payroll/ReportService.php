<?php

namespace App\Services\Payroll;

use App\Models\HR\DtrRecord;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\PayrollRun;
use App\Models\User;
use Carbon\Carbon;
use Mpdf\Mpdf;

class ReportService
{
    private const AGENCY_NAME = 'Philippine Science High School - Caraga Region Campus';

    // ── Payroll Register ───────────────────────────────────────────────────────

    /**
     * Payroll Register — full earnings/deductions per employee for a run.
     */
    public function payrollRegister(PayrollRun $run): string
    {
        $records = PayrollRecord::where('payroll_run_id', $run->id)
            ->with(['user', 'allowances.allowanceType', 'deductions.allowanceType'])
            ->orderBy('user_id')
            ->get();

        $months  = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        $month   = $months[$run->month] ?? $run->month;
        $period  = $run->period === '1st' ? '1st–15th' : '16th–End of Month';

        $rows = '';
        foreach ($records as $r) {
            $rows .= $this->registerRow($r);
        }

        $html = $this->reportShell(
            "Payroll Register",
            "{$month} {$run->year} — {$period} &bull; {$run->control_no}",
            $this->registerTable($rows, $run)
        );

        return $this->renderPdf($html, 'A4', 'L');
    }

    /**
     * Deductions Register — per deduction type for a run.
     */
    public function deductionsRegister(PayrollRun $run): string
    {
        $records = PayrollRecord::where('payroll_run_id', $run->id)
            ->with(['user', 'deductions.allowanceType'])
            ->orderBy('user_id')
            ->get();

        $months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        $month  = $months[$run->month] ?? $run->month;

        // Pivot: deduction_type → [user → amount]
        $deductionTypes = [];
        foreach ($records as $r) {
            foreach ($r->deductions as $d) {
                $code = $d->allowanceType?->code ?? 'OTHER';
                $name = $d->allowanceType?->name ?? 'Other';
                $deductionTypes[$code] = $name;
            }
        }

        $rows = '';
        $colTotals = array_fill_keys(array_keys($deductionTypes), 0);

        foreach ($records as $r) {
            $byType = $r->deductions->keyBy(fn ($d) => $d->allowanceType?->code ?? 'OTHER');
            $cols = '';
            foreach ($deductionTypes as $code => $name) {
                $amt = (float) ($byType[$code]?->amount ?? 0);
                $colTotals[$code] += $amt;
                $cols .= "<td style='text-align:right;padding:2px 6px;'>" . ($amt > 0 ? '₱' . number_format($amt, 2) : '—') . "</td>";
            }
            $totalDed = number_format($r->total_deductions, 2);
            $rows .= "<tr><td style='padding:2px 6px;'>{$this->e($r->user?->name ?? '—')}</td>{$cols}<td style='text-align:right;font-weight:bold;padding:2px 6px;'>₱{$totalDed}</td></tr>\n";
        }

        // Header
        $typeHeaders = '';
        foreach ($deductionTypes as $code => $name) {
            $typeHeaders .= "<th style='{$this->thStyle()}'>" . $this->e($name) . "</th>";
        }

        $typeTotals = '';
        foreach ($deductionTypes as $code => $name) {
            $typeTotals .= "<td style='text-align:right;font-weight:bold;padding:2px 6px;border-top:2px solid #1e293b;'>₱" . number_format($colTotals[$code], 2) . "</td>";
        }
        $grandTotal = number_format(array_sum($colTotals), 2);

        $table = "
        <table style='width:100%;border-collapse:collapse;font-size:7.5pt;'>
          <thead>
            <tr>
              <th style='{$this->thStyle()}'>Employee</th>
              {$typeHeaders}
              <th style='{$this->thStyle()}'>Total Deductions</th>
            </tr>
          </thead>
          <tbody>{$rows}</tbody>
          <tfoot>
            <tr style='background:#f8fafc;'>
              <td style='font-weight:bold;padding:2px 6px;border-top:2px solid #1e293b;'>TOTAL</td>
              {$typeTotals}
              <td style='text-align:right;font-weight:bold;padding:2px 6px;border-top:2px solid #1e293b;'>₱{$grandTotal}</td>
            </tr>
          </tfoot>
        </table>";

        $html = $this->reportShell(
            "Deductions Register",
            "{$month} {$run->year} — {$run->control_no}",
            $table
        );

        return $this->renderPdf($html, 'A4', 'L');
    }

    /**
     * DTR Monthly Summary — attendance breakdown per employee for a month.
     */
    public function dtrMonthlySummary(int $year, int $month): string
    {
        $from = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $to   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $users = User::where('status', 'active')
            ->whereHas('employeeProfile')
            ->orderBy('name')
            ->get();

        $months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        $mLabel = $months[$month] ?? $month;

        $rows = '';
        foreach ($users as $user) {
            $records = DtrRecord::where('user_id', $user->id)
                ->whereYear('work_date', $year)
                ->whereMonth('work_date', $month)
                ->get();

            $present  = $records->where('attendance_status', 'present')->count();
            $absent   = $records->where('attendance_status', 'absent')->count();
            $late     = $records->where('late_minutes', '>', 0)->count();
            $halfDay  = $records->where('attendance_status', 'half_day')->count();
            $leave    = $records->where('attendance_status', 'on_leave')->count();
            $totalLate = $records->sum('late_minutes');
            $totalUt   = $records->sum('undertime_minutes');
            $totalOt   = $records->sum('overtime_minutes');
            $totalHrs  = $records->sum('hours_worked');

            $rows .= "<tr>
              <td style='padding:2px 6px;'>{$this->e($user->name)}</td>
              <td style='text-align:center;padding:2px 4px;'>{$present}</td>
              <td style='text-align:center;padding:2px 4px;color:#dc2626;'>{$absent}</td>
              <td style='text-align:center;padding:2px 4px;color:#d97706;'>{$late}</td>
              <td style='text-align:center;padding:2px 4px;color:#ea580c;'>{$halfDay}</td>
              <td style='text-align:center;padding:2px 4px;color:#2563eb;'>{$leave}</td>
              <td style='text-align:right;padding:2px 4px;color:#d97706;'>{$totalLate}</td>
              <td style='text-align:right;padding:2px 4px;color:#ea580c;'>{$totalUt}</td>
              <td style='text-align:right;padding:2px 4px;color:#16a34a;'>{$totalOt}</td>
              <td style='text-align:right;padding:2px 4px;font-weight:bold;'>" . number_format($totalHrs, 2) . "</td>
            </tr>\n";
        }

        $table = "
        <table style='width:100%;border-collapse:collapse;font-size:7.5pt;'>
          <thead>
            <tr>
              <th style='{$this->thStyle()}'>Employee</th>
              <th style='{$this->thStyle()}'>Present</th>
              <th style='{$this->thStyle()}'>Absent</th>
              <th style='{$this->thStyle()}'>Late Days</th>
              <th style='{$this->thStyle()}'>Half Day</th>
              <th style='{$this->thStyle()}'>On Leave</th>
              <th style='{$this->thStyle()}'>Late (min)</th>
              <th style='{$this->thStyle()}'>UT (min)</th>
              <th style='{$this->thStyle()}'>OT (min)</th>
              <th style='{$this->thStyle()}'>Total Hrs</th>
            </tr>
          </thead>
          <tbody>{$rows}</tbody>
        </table>";

        $html = $this->reportShell(
            "DTR Monthly Summary",
            "{$mLabel} {$year}",
            $table
        );

        return $this->renderPdf($html, 'A4', 'L');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function registerRow(PayrollRecord $r): string
    {
        $allowanceTotal = $r->allowances->sum('amount');
        return "<tr>
          <td style='padding:2px 6px;'>{$this->e($r->user?->name ?? '—')}</td>
          <td style='text-align:right;padding:2px 4px;'>" . number_format($r->monthly_salary, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;'>" . number_format($r->basic_pay, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;'>" . number_format($allowanceTotal, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;font-weight:bold;'>" . number_format($r->gross_pay, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;color:#dc2626;'>" . number_format($r->absence_deduction + $r->tardiness_deduction, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;color:#dc2626;'>" . number_format($r->total_deductions - $r->absence_deduction - $r->tardiness_deduction, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;color:#dc2626;'>" . number_format($r->withheld_tax, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;font-weight:bold;color:#dc2626;'>" . number_format($r->total_deductions, 2) . "</td>
          <td style='text-align:right;padding:2px 4px;font-weight:bold;font-size:8pt;'>" . number_format($r->net_pay, 2) . "</td>
        </tr>\n";
    }

    private function registerTable(string $rows, PayrollRun $run): string
    {
        $totals = [
            'gross'      => number_format($run->total_gross, 2),
            'deductions' => number_format($run->total_deductions, 2),
            'net'        => number_format($run->total_net, 2),
        ];

        return "
        <table style='width:100%;border-collapse:collapse;font-size:7.5pt;'>
          <thead>
            <tr>
              <th style='{$this->thStyle()}'>Employee</th>
              <th style='{$this->thStyle()}'>Monthly Salary</th>
              <th style='{$this->thStyle()}'>Basic Pay</th>
              <th style='{$this->thStyle()}'>Allowances</th>
              <th style='{$this->thStyle()}'>Gross Pay</th>
              <th style='{$this->thStyle()}'>Abs/Late Ded.</th>
              <th style='{$this->thStyle()}'>Mandatory Ded.</th>
              <th style='{$this->thStyle()}'>WHT</th>
              <th style='{$this->thStyle()}'>Total Ded.</th>
              <th style='{$this->thStyle()}'>Net Pay</th>
            </tr>
          </thead>
          <tbody>{$rows}</tbody>
          <tfoot>
            <tr style='background:#f8fafc;'>
              <td colspan='4' style='font-weight:bold;padding:2px 6px;border-top:2px solid #1e293b;'>TOTAL ({$run->employee_count} employees)</td>
              <td style='text-align:right;font-weight:bold;padding:2px 4px;border-top:2px solid #1e293b;'>₱{$totals['gross']}</td>
              <td colspan='3' style='border-top:2px solid #1e293b;'></td>
              <td style='text-align:right;font-weight:bold;padding:2px 4px;color:#dc2626;border-top:2px solid #1e293b;'>₱{$totals['deductions']}</td>
              <td style='text-align:right;font-weight:bold;padding:2px 4px;border-top:2px solid #1e293b;'>₱{$totals['net']}</td>
            </tr>
          </tfoot>
        </table>";
    }

    private function reportShell(string $title, string $subtitle, string $body): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 8pt; color: #1e293b; margin: 0; }
  .report-header { border-bottom: 2px solid #1e293b; padding-bottom: 6px; margin-bottom: 10px; }
  .agency { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
  .report-title { font-size: 11pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 3px 0 1px; }
  .subtitle { font-size: 7.5pt; color: #475569; }
  .report-footer { margin-top: 12px; font-size: 6.5pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; text-align: right; }
</style>
</head>
<body>
<div class="report-header">
  <div class="agency">{$this->e(self::AGENCY_NAME)}</div>
  <div class="report-title">{$this->e($title)}</div>
  <div class="subtitle">{$subtitle}</div>
</div>

{$body}

<div class="report-footer">
  Generated: {$this->e(now()->format('d M Y H:i'))} &bull; {$this->e(self::AGENCY_NAME)}
</div>
</body>
</html>
HTML;
    }

    private function thStyle(): string
    {
        return 'background:#1e293b;color:#fff;padding:3px 6px;text-align:left;font-size:7pt;font-weight:bold;white-space:nowrap;';
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function renderPdf(string $html, string $format = 'A4', string $orientation = 'P'): string
    {
        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => $format,
            'orientation'   => $orientation,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 10,
            'margin_bottom' => 10,
            'margin_footer' => 5,
            'default_font'  => 'Arial',
            'default_font_size' => 8,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
