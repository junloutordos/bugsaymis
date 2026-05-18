<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollItem;
use App\Models\User;
use Illuminate\Http\Response;
use Mpdf\Mpdf;

class PayrollPdfService
{
    // All numeric columns that are summed across batches when building the combined payslip
    private const NUMERIC_COLS = [
        'basic_salary', 'salary_increase', 'additional_compensation',
        'pera', 'sala', 'subsistence_allowance', 'laundry_allowance',
        'hazard_pay', 'longevity_pay', 'others_bonuses', 'year_end_bonus', 'cash_gift',
        'clothing_allowance', 'midyear_bonus', 'cna_incentive', 'gross_earnings',
        'lawop', 'pvp_overpayment', 'ob_travel_seminar', 'gsis_contribution', 'bir_tax',
        'wht_hazard', 'wht_cna', 'longevity_tax',
        'philhealth_contribution', 'philhealth_differential',
        'hdmf_contribution', 'hdmf_additional',
        'gsis_salary_loan', 'gsis_policy_loan', 'gsis_consolidated_loan',
        'gsis_emergency_loan', 'gsis_mpl', 'gsis_gfal', 'gsis_cpl', 'gsis_mpl_lite',
        'hdmf_salary_loan', 'hdmf_calamity', 'hdmf_housing', 'hdmf_multipurpose', 'hdmf_mp2',
        'landbank_loan', 'credit_union',
        'total_deductions', 'net_pay', 'first_half_amount', 'second_half_amount',
    ];

    /**
     * Generate a payslip PDF combining all disbursements for this employee+period.
     * The triggering item's batch is used for the header (credit dates, disbursement label).
     */
    public function generate(PayrollItem $item): string
    {
        [$preparedBy, $certifiedBy] = $this->signatories();

        $combined          = $this->buildCombined($item);
        $disbursementLabel = $this->combinedDisbursementLabel($item);

        $html = view('payroll.payslip', [
            'item'               => $combined,
            'batch'              => $item->batch,
            'preparedBy'         => $preparedBy,
            'certifiedBy'        => $certifiedBy,
            'disbursementLabel'  => $disbursementLabel,
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
     * Build a combined PayrollItem that sums numeric columns from all batches
     * the employee has for this period. Non-numeric meta (name, position, etc.)
     * and adjustments come from the triggering item.
     */
    public function buildCombined(PayrollItem $item): PayrollItem
    {
        if (!$item->matched_user_id) {
            return $item;
        }

        // Release group: sum across all sibling batches for this employee
        $batch = $item->relationLoaded('batch') ? $item->getRelation('batch') : $item->batch;
        if ($batch?->release_id) {
            $siblingIds = PayrollBatch::where('release_id', $batch->release_id)->pluck('id');
            $allItems   = PayrollItem::whereIn('batch_id', $siblingIds)
                ->where('matched_user_id', $item->matched_user_id)
                ->get();
        } else {
            $allItems = PayrollItem::where('matched_user_id', $item->matched_user_id)
                ->where('month', $item->month)
                ->where('year', $item->year)
                ->get();
        }

        if ($allItems->count() <= 1) {
            return $item;
        }

        // replicate() copies all attributes without persisting; override numeric cols with sums
        $combined = $item->replicate();
        foreach (self::NUMERIC_COLS as $col) {
            $combined->$col = round((float) $allItems->sum($col), 2);
        }

        // Preserve already-loaded relationships from the triggering item
        if ($item->relationLoaded('employee')) {
            $combined->setRelation('employee', $item->getRelation('employee'));
        }
        if ($item->relationLoaded('batch')) {
            $combined->setRelation('batch', $item->getRelation('batch'));
        }

        return $combined;
    }

    /**
     * Build a combined disbursement label from all batches the employee has for this period.
     * E.g. "Monthly Salary + Clothing Allowance"
     */
    public function combinedDisbursementLabel(PayrollItem $item): string
    {
        $batch = $item->relationLoaded('batch') ? $item->getRelation('batch') : $item->batch;

        if (!$item->matched_user_id) {
            return $batch?->disbursementLabel() ?? '';
        }

        // Release group: show type label + period range
        if ($batch?->release_id) {
            $siblings = PayrollBatch::where('release_id', $batch->release_id)
                ->orderBy('year')->orderBy('month')
                ->get(['month', 'year', 'disbursement_type', 'label']);
            $baseLabel = $batch->disbursementLabel();
            if ($siblings->count() > 1) {
                $first = $siblings->first();
                $last  = $siblings->last();
                $firstStr = \Carbon\Carbon::createFromDate($first->year, $first->month, 1)->format('M Y');
                $lastStr  = \Carbon\Carbon::createFromDate($last->year, $last->month, 1)->format('M Y');
                return "{$baseLabel} ({$firstStr}–{$lastStr})";
            }
            return $baseLabel;
        }

        $labels = PayrollItem::where('matched_user_id', $item->matched_user_id)
            ->where('month', $item->month)
            ->where('year', $item->year)
            ->with('batch')
            ->get()
            ->pluck('batch')
            ->filter()
            ->map(fn($b) => $b->disbursementLabel())
            ->unique()
            ->values()
            ->implode(' + ');

        return $labels ?: ($batch?->disbursementLabel() ?? '');
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
