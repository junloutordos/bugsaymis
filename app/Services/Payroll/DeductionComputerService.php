<?php

namespace App\Services\Payroll;

/**
 * Computes Philippine government statutory deductions and withholding tax.
 *
 * References:
 *  - GSIS Act (RA 8291): employee share 11% (9% retirement + 2% life)
 *  - PhilHealth (RA 11223 + PHIC Circulars): 5% of monthly basic salary,
 *    employee share = 2.5%, floor ₱10,000, ceiling ₱100,000
 *  - Pag-IBIG (RA 9679): mandatory ₱100/month employee share
 *  - BIR TRAIN Law (RA 10963, effective 2023 onwards) monthly WHT table
 */
class DeductionComputerService
{
    // ── GSIS ─────────────────────────────────────────────────────────────────

    public function gsisRetirement(float $monthlyBasic): float
    {
        return round($monthlyBasic * 0.09, 4);
    }

    public function gsisLife(float $monthlyBasic): float
    {
        return round($monthlyBasic * 0.02, 4);
    }

    public function gsisTotal(float $monthlyBasic): float
    {
        return $this->gsisRetirement($monthlyBasic) + $this->gsisLife($monthlyBasic);
    }

    // ── PhilHealth ────────────────────────────────────────────────────────────

    /**
     * Employee share = 2.5% of monthly basic salary.
     * Floor income = ₱10,000 (min premium = ₱250/month).
     * Ceiling income = ₱100,000 (max premium = ₱2,500/month).
     */
    public function philhealth(float $monthlyBasic): float
    {
        $basis = max(10_000, min(100_000, $monthlyBasic));
        return round($basis * 0.025, 4);   // employee 2.5% share
    }

    // ── Pag-IBIG ─────────────────────────────────────────────────────────────

    public function pagibig(): float
    {
        return 100.0;
    }

    // ── Withholding Tax (BIR TRAIN Law 2023) ─────────────────────────────────

    /**
     * Compute monthly BIR withholding tax on taxable compensation income.
     *
     * Taxable income = gross compensation
     *                - non-taxable mandatory contributions (GSIS + PhilHealth + Pag-IBIG)
     *
     * TRAIN Law 2023+ monthly table:
     *   ≤ ₱20,833                        →  0%
     *   > ₱20,833  – ₱33,332             →  20% × (excess over ₱20,833)
     *   > ₱33,332  – ₱66,666             →  ₱2,500 + 25% × (excess over ₱33,332)
     *   > ₱66,666  – ₱166,666            →  ₱10,833 + 30% × (excess over ₱66,666)
     *   > ₱166,666 – ₱666,666            →  ₱40,833.33 + 32% × (excess over ₱166,666)
     *   > ₱666,666                        →  ₱200,833.33 + 35% × (excess over ₱666,666)
     */
    public function withholdingTax(float $monthlyTaxableIncome): float
    {
        if ($monthlyTaxableIncome <= 0) {
            return 0.0;
        }

        $t = $monthlyTaxableIncome;

        $tax = match (true) {
            $t <= 20_833                    => 0,
            $t <= 33_332                    => ($t - 20_833) * 0.20,
            $t <= 66_666                    => 2_500 + ($t - 33_332) * 0.25,
            $t <= 166_666                   => 10_833 + ($t - 66_666) * 0.30,
            $t <= 666_666                   => 40_833.33 + ($t - 166_666) * 0.32,
            default                         => 200_833.33 + ($t - 666_666) * 0.35,
        };

        return round($tax, 4);
    }

    /**
     * Convenience: compute taxable income and then the monthly WHT.
     *
     * @param float $monthlyGross     gross compensation (basic + taxable allowances)
     * @param float $gsisMonthly      GSIS total employee share (non-taxable)
     * @param float $philhealthMonthly PhilHealth employee share (non-taxable)
     * @param float $pagibigMonthly   Pag-IBIG employee share (non-taxable)
     */
    public function monthlyWithholdingTax(
        float $monthlyGross,
        float $gsisMonthly,
        float $philhealthMonthly,
        float $pagibigMonthly,
    ): float {
        $taxableIncome = $monthlyGross - $gsisMonthly - $philhealthMonthly - $pagibigMonthly;
        return $this->withholdingTax(max(0, $taxableIncome));
    }

    // ── Period helpers ────────────────────────────────────────────────────────

    /**
     * Split a monthly deduction across a 2-period month.
     * 1st period → first half deducted; 2nd period → remainder.
     * Using 50/50 split for predictability.
     */
    public function splitForPeriod(float $monthlyAmount, string $period): float
    {
        return round($monthlyAmount / 2, 4);
    }
}
