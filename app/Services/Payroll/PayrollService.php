<?php

namespace App\Services\Payroll;

use App\Models\HR\DtrRecord;
use App\Models\HR\EmployeeProfile;
use App\Models\Payroll\AllowanceType;
use App\Models\Payroll\EmployeeDeductionConfig;
use App\Models\Payroll\PayrollAllowance;
use App\Models\Payroll\PayrollDeduction;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\PayrollRun;
use App\Models\SalaryGrade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        protected DeductionComputerService $deductions
    ) {}

    /**
     * Compute payroll for one employee in a given PayrollRun.
     * Creates/updates PayrollRecord + child PayrollAllowance/Deduction rows.
     */
    public function computeForEmployee(PayrollRun $run, User $user): PayrollRecord
    {
        $profile = $user->employeeProfile;

        // ── 1. Salary ─────────────────────────────────────────────────────────
        [$salaryGrade, $step, $monthlyRate] = $this->resolveSalary($profile);

        // ── 2. DTR summary for the period ─────────────────────────────────────
        $dtrSummary = $this->getDtrSummary($user->id, $run->period_start, $run->period_end);

        // ── 3. Working days in the period ─────────────────────────────────────
        $workingDaysInPeriod = $this->countWorkingDays($run->period_start, $run->period_end);
        $dailyRate           = $workingDaysInPeriod > 0
            ? ($monthlyRate / 2) / $workingDaysInPeriod
            : 0;

        // ── 4. Compute basic_pay ──────────────────────────────────────────────
        // Half-month base
        $halfMonthBase = $monthlyRate / 2;

        // Absence deduction (absence_days × daily_rate)
        $absenceDeduction = round($dtrSummary['days_absent'] * $dailyRate, 4);

        // Tardiness deduction (late_minutes + undertime_minutes) × (daily_rate / 480)
        $tardinessDeduction = round(
            ($dtrSummary['late_minutes'] + $dtrSummary['undertime_minutes'])
                * ($dailyRate / 480),
            4
        );

        // LWOP: if attendance_status = on_leave with is_without_pay → treat as absence
        $lwopDeduction = 0;

        $basicPay = max(0, $halfMonthBase - $absenceDeduction - $tardinessDeduction - $lwopDeduction);

        // ── 5. Mandatory allowances ───────────────────────────────────────────
        $mandatoryAllowances = AllowanceType::active()
            ->allowances()
            ->mandatory()
            ->get();

        $allowanceRows    = [];
        $totalAllowances  = 0;
        $taxableAllowances = 0;

        foreach ($mandatoryAllowances as $at) {
            $amount = $this->resolveAllowanceAmount($at, $profile, $run->period);

            if ($amount <= 0) continue;

            $allowanceRows[]   = ['type' => $at, 'amount' => $amount, 'taxable' => $at->is_taxable];
            $totalAllowances  += $amount;
            if ($at->is_taxable) {
                $taxableAllowances += $amount;
            }
        }

        $grossPay = $basicPay + $totalAllowances;

        // ── 6. Statutory deductions (monthly amounts, split for period) ────────
        $gsisMonthly        = $this->deductions->gsisTotal($monthlyRate);
        $philhealthMonthly  = $this->deductions->philhealth($monthlyRate);
        $pagibigMonthly     = $this->deductions->pagibig();

        $gsisPeriod       = $this->deductions->splitForPeriod($gsisMonthly, $run->period);
        $philhealthPeriod = $this->deductions->splitForPeriod($philhealthMonthly, $run->period);
        $pagibigPeriod    = $this->deductions->splitForPeriod($pagibigMonthly, $run->period);

        // Withholding tax — computed on monthly basis, deducted in 2nd period
        $withheldTax = 0;
        if ($run->period === '2nd') {
            $monthlyGross = ($basicPay + $taxableAllowances) * 2;
            $withheldTax  = $this->deductions->monthlyWithholdingTax(
                $monthlyGross,
                $gsisMonthly,
                $philhealthMonthly,
                $pagibigMonthly,
            );
        }

        // ── 7. Load statutory deduction types ─────────────────────────────────
        $gsisRetType  = AllowanceType::where('code', 'GSIS-RET')->first();
        $gsisLifeType = AllowanceType::where('code', 'GSIS-LIFE')->first();
        $philhType    = AllowanceType::where('code', 'PHILHEALTH')->first();
        $pagibigType  = AllowanceType::where('code', 'PAGIBIG')->first();
        $whtType      = AllowanceType::where('code', 'BIR-WHT')->first();

        $deductionRows = [];

        if ($gsisRetType) {
            $deductionRows[] = [
                'type'   => $gsisRetType,
                'amount' => $this->deductions->splitForPeriod(
                    $this->deductions->gsisRetirement($monthlyRate), $run->period
                ),
            ];
        }
        if ($gsisLifeType) {
            $deductionRows[] = [
                'type'   => $gsisLifeType,
                'amount' => $this->deductions->splitForPeriod(
                    $this->deductions->gsisLife($monthlyRate), $run->period
                ),
            ];
        }
        if ($philhType) {
            $deductionRows[] = ['type' => $philhType, 'amount' => $philhealthPeriod];
        }
        if ($pagibigType) {
            $deductionRows[] = ['type' => $pagibigType, 'amount' => $pagibigPeriod];
        }
        if ($whtType && $withheldTax > 0) {
            $deductionRows[] = ['type' => $whtType, 'amount' => $withheldTax];
        }

        // ── 8. Voluntary / employee-specific deductions ───────────────────────
        $voluntaryDeductions = EmployeeDeductionConfig::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($q) use ($run) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', $run->period_end);
            })
            ->where(function ($q) use ($run) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $run->period_start);
            })
            ->with('allowanceType')
            ->get();

        foreach ($voluntaryDeductions as $config) {
            $amount = $config->amount ?? $config->allowanceType->default_amount ?? 0;
            if ($amount > 0) {
                $deductionRows[] = [
                    'type'   => $config->allowanceType,
                    'amount' => $amount,
                    'ref'    => $config->reference_no,
                ];
            }
        }

        // ── 9. Totals ─────────────────────────────────────────────────────────
        $totalDeductions = collect($deductionRows)->sum('amount')
            + $absenceDeduction + $tardinessDeduction + $lwopDeduction;

        $netPay = $grossPay - $totalDeductions;

        // ── 10. Persist ────────────────────────────────────────────────────────
        return DB::transaction(function () use (
            $run, $user, $salaryGrade, $step, $monthlyRate,
            $basicPay, $dtrSummary, $absenceDeduction, $tardinessDeduction,
            $lwopDeduction, $grossPay, $totalDeductions, $netPay, $withheldTax,
            $allowanceRows, $deductionRows
        ) {
            /** @var PayrollRecord $record */
            $record = PayrollRecord::updateOrCreate(
                ['payroll_run_id' => $run->id, 'user_id' => $user->id],
                [
                    'salary_grade'         => $salaryGrade,
                    'step'                 => $step,
                    'monthly_salary'       => $monthlyRate,
                    'basic_pay'            => round($basicPay, 4),
                    'days_worked'          => $dtrSummary['days_worked'],
                    'days_absent'          => $dtrSummary['days_absent'],
                    'late_minutes'         => $dtrSummary['late_minutes'],
                    'undertime_minutes'    => $dtrSummary['undertime_minutes'],
                    'overtime_minutes'     => $dtrSummary['overtime_minutes'],
                    'tardiness_deduction'  => round($tardinessDeduction, 4),
                    'absence_deduction'    => round($absenceDeduction, 4),
                    'lwop_deduction'       => round($lwopDeduction, 4),
                    'gross_pay'            => round($grossPay, 4),
                    'total_deductions'     => round($totalDeductions, 4),
                    'net_pay'              => round($netPay, 4),
                    'withheld_tax'         => round($withheldTax, 4),
                    'status'               => 'computed',
                ]
            );

            // Rebuild allowances
            $record->allowances()->delete();
            foreach ($allowanceRows as $row) {
                PayrollAllowance::create([
                    'payroll_record_id' => $record->id,
                    'allowance_type_id' => $row['type']->id,
                    'amount'            => round($row['amount'], 4),
                    'is_taxable'        => $row['taxable'] ?? false,
                ]);
            }

            // Rebuild deductions
            $record->deductions()->delete();
            foreach ($deductionRows as $row) {
                PayrollDeduction::create([
                    'payroll_record_id' => $record->id,
                    'allowance_type_id' => $row['type']->id,
                    'amount'            => round($row['amount'], 4),
                    'reference_no'      => $row['ref'] ?? null,
                ]);
            }

            return $record;
        });
    }

    /**
     * After all employee records are computed, update PayrollRun summary totals.
     */
    public function updateRunTotals(PayrollRun $run): void
    {
        $run->refresh();

        $records = $run->payrollRecords()->where('status', '!=', 'void')->get();

        $run->update([
            'employee_count'   => $records->count(),
            'total_gross'      => $records->sum('gross_pay'),
            'total_deductions' => $records->sum('total_deductions'),
            'total_net'        => $records->sum('net_pay'),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function resolveSalary(?EmployeeProfile $profile): array
    {
        if (! $profile) {
            return [1, 1, 0.0];
        }

        $sg = SalaryGrade::where('grade', $profile->salary_grade_id ?? 1)
            ->where('step', $profile->step ?? 1)
            ->where('is_current', true)
            ->first();

        $rate = $sg ? (float) $sg->monthly_rate : 0.0;

        return [$profile->salary_grade_id ?? 1, $profile->step ?? 1, $rate];
    }

    private function getDtrSummary(int $userId, $periodStart, $periodEnd): array
    {
        $records = DtrRecord::where('user_id', $userId)
            ->whereBetween('work_date', [$periodStart, $periodEnd])
            ->get();

        return [
            'days_worked'       => $records->where('attendance_status', 'present')->count()
                                 + $records->where('attendance_status', 'on_official_business')->count(),
            'days_absent'       => $records->where('attendance_status', 'absent')->count(),
            'late_minutes'      => $records->sum('late_minutes'),
            'undertime_minutes' => $records->sum('undertime_minutes'),
            'overtime_minutes'  => $records->sum('overtime_minutes'),
        ];
    }

    private function countWorkingDays($from, $to): int
    {
        $start = \Carbon\Carbon::parse($from);
        $end   = \Carbon\Carbon::parse($to);
        $count = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (! $d->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }

    private function resolveAllowanceAmount(AllowanceType $at, ?EmployeeProfile $profile, string $period): float
    {
        $amount = (float) ($at->default_amount ?? 0);

        if (! $at->is_fixed_amount && $profile) {
            // Percentage of monthly salary
            $sg = $profile ? SalaryGrade::where('grade', $profile->salary_grade_id ?? 1)
                ->where('step', $profile->step ?? 1)
                ->where('is_current', true)
                ->value('monthly_rate') : 0;
            $amount = (float) $sg * $amount;
        }

        // Split per period
        return round($amount / 2, 4);
    }
}
