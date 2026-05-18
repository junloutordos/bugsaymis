<?php

namespace App\Services\Payroll;

class PayrollParserService
{
    private const FOOTER_PATTERNS = [
        'prepared by', 'certified correct', 'total', 'sub-total',
        'job order', 'cos', 'grand total', 'noted by',
        'staff', 'faculty', 'teaching', 'non-teaching',
    ];

    // CSV column header → DB field (covers all disbursement types)
    private const CSV_COLUMNS = [
        // Identity
        'Name'                       => 'employee_name_raw',
        'Employee No.'               => 'employee_no',
        'Position'                   => 'position',
        // Earnings — Monthly Salary
        'Salaries and Wages-Regular' => 'basic_salary',
        'PERA'                       => 'pera',
        'Gross Amount Earned'        => 'gross_earnings',
        // Earnings — Allowances (separate disbursements)
        'Hazard Pay'                        => 'hazard_pay',
        'WHT Hazard Pay'                    => 'wht_hazard',
        'Subsistence and Laundry'           => 'sala',
        // SALA breakdown columns (new template)
        'Subsistence Allowance'             => 'subsistence_allowance',
        'Laundry Allowance'                 => 'laundry_allowance',
        'Gross Amount'                      => 'gross_earnings',
        // Year-End Bonus and Cash Gift
        'Year-End Bonus'                    => 'year_end_bonus',
        'Cash Gift'                         => 'cash_gift',
        'Less: BIR Tax Withheld'            => 'bir_tax',
        'NET AMOUNT'                        => 'net_pay',
        // Clothing Allowance
        'Gross'                             => 'clothing_allowance',
        'Deductions'                        => 'total_deductions',
        'Net'                               => 'net_pay',
        'Longevity Pay'                     => 'longevity_pay',
        'Longevity Pay Tax'          => 'longevity_tax',
        'Others (Bonus/Incentives/CA)' => 'others_bonuses',
        // Deductions — General
        'Tardiness/AWOP'                                   => 'lawop',
        'Late/Absent/Work at Home/On Quarantine'           => 'lawop',
        'OB/Travel/Seminar with meals'                     => 'ob_travel_seminar',
        'Amount'                                           => 'total_deductions',
        'Net SALA'                                         => 'net_pay',
        'BIR Withholding Tax'                              => 'bir_tax',
        // GSIS
        'RLIP EE'                    => 'gsis_contribution',
        'Pol. Loan'                  => 'gsis_policy_loan',
        'Emergency/Cal. Loan'        => 'gsis_emergency_loan',
        'GFAL'                       => 'gsis_gfal',
        'GSIS MPL'                   => 'gsis_mpl',
        'CPL'                        => 'gsis_cpl',
        'Multi-purpose Life'         => 'gsis_mpl_lite',
        // HDMF / PAG-IBIG
        'EE Share'                   => 'hdmf_contribution',
        'MP2'                        => 'hdmf_mp2',
        'PAG-IBIG MPL'               => 'hdmf_multipurpose',
        'Calamity Loan'              => 'hdmf_calamity',
        'HLF'                        => 'hdmf_housing',
        // Other deductions
        'Landbank'                   => 'landbank_loan',
        'PHIC EE Share'              => 'philhealth_contribution',
        // Totals
        'Total Deductions'           => 'total_deductions',
        'Net Amount Due'             => 'net_pay',
        'First Half'                 => 'first_half_amount',
        'Second Half'                => 'second_half_amount',
    ];

    // Extra earnings columns injected after "Gross Amount Earned" when combined with monthly salary
    private const EXTRA_EARNINGS = [
        'hazard_pay'    => ['Hazard Pay'],
        'sala'          => ['Subsistence Allowance', 'Laundry Allowance'],
        'longevity_pay' => ['Longevity Pay'],
        'other'         => ['Others (Bonus/Incentives/CA)'],
    ];

    // Extra deduction columns injected before "Total Deductions" when combined with monthly salary
    private const EXTRA_DEDUCTIONS = [
        'hazard_pay'    => ['WHT Hazard Pay'],
        'longevity_pay' => ['Longevity Pay Tax'],
    ];

    // Columns included per disbursement type template
    private const DISBURSEMENT_TEMPLATES = [
        'monthly_salary' => [
            'Name', 'Employee No.', 'Position',
            'Salaries and Wages-Regular', 'PERA', 'Gross Amount Earned',
            'Tardiness/AWOP', 'BIR Withholding Tax',
            'RLIP EE', 'Pol. Loan', 'Emergency/Cal. Loan', 'GFAL', 'GSIS MPL', 'CPL', 'Multi-purpose Life',
            'EE Share', 'MP2', 'PAG-IBIG MPL', 'Calamity Loan', 'HLF',
            'Landbank', 'PHIC EE Share',
            'Total Deductions', 'Net Amount Due', 'First Half', 'Second Half',
        ],
        'hazard_pay' => [
            'Name', 'Employee No.', 'Position', 'Hazard Pay', 'WHT Hazard Pay', 'Net Amount Due',
        ],
        'sala' => [
            'Name', 'Employee No.', 'Position',
            'Subsistence Allowance', 'Laundry Allowance', 'Gross Amount',
            'Late/Absent/Work at Home/On Quarantine', 'OB/Travel/Seminar with meals', 'Amount',
            'Net SALA',
        ],
        'longevity_pay' => [
            'Name', 'Employee No.', 'Position', 'Longevity Pay', 'Longevity Pay Tax', 'Net Amount Due',
        ],
        'other' => [
            'Name', 'Employee No.', 'Position', 'Others (Bonus/Incentives/CA)', 'Net Amount Due',
        ],
        'year_end_bonus' => [
            'Name', 'Employee No.', 'Position',
            'Year-End Bonus', 'Cash Gift', 'Gross Amount',
            'Less: BIR Tax Withheld', 'NET AMOUNT',
        ],
        'clothing_allowance' => [
            'Name', 'Employee No.', 'Position',
            'Gross', 'Deductions', 'Net',
        ],
    ];

    /**
     * Generate downloadable CSV template for the given disbursement type(s).
     *
     * @param string|string[] $types
     */
    public function csvTemplate(array|string $types = 'monthly_salary'): string
    {
        $types = (array) $types;

        if (in_array('monthly_salary', $types)) {
            // Base: full monthly salary columns
            $headers = self::DISBURSEMENT_TEMPLATES['monthly_salary'];

            // Splice in extra earnings after "Gross Amount Earned"
            $extras = [];
            foreach ($types as $t) {
                if ($t === 'monthly_salary') continue;
                foreach (self::EXTRA_EARNINGS[$t] ?? [] as $col) {
                    if (!in_array($col, $extras)) $extras[] = $col;
                }
            }
            if ($extras) {
                $pos = array_search('Gross Amount Earned', $headers);
                if ($pos !== false) array_splice($headers, $pos + 1, 0, $extras);
            }

            // Splice in extra deductions before "Total Deductions"
            $extraDeds = [];
            foreach ($types as $t) {
                if ($t === 'monthly_salary') continue;
                foreach (self::EXTRA_DEDUCTIONS[$t] ?? [] as $col) {
                    if (!in_array($col, $extraDeds)) $extraDeds[] = $col;
                }
            }
            if ($extraDeds) {
                $pos = array_search('Total Deductions', $headers);
                if ($pos !== false) array_splice($headers, $pos, 0, $extraDeds);
            }
        } else {
            // Non-monthly: union of all selected type columns, no duplicates
            $headers = [];
            foreach ($types as $t) {
                foreach (self::DISBURSEMENT_TEMPLATES[$t] ?? [] as $col) {
                    if (!in_array($col, $headers)) $headers[] = $col;
                }
            }
        }

        $pos     = array_flip($headers);
        $example = array_fill(0, count($headers), '');

        $set = function (string $col, string $val) use (&$example, $pos) {
            if (isset($pos[$col])) $example[$pos[$col]] = $val;
        };

        $set('Name',                         'DELA CRUZ, Juan A.');
        $set('Employee No.',                  'pshscrc13-00123');
        $set('Position',                      'Administrative Assistant II');
        $set('Salaries and Wages-Regular',    '33947.00');
        $set('PERA',                          '2000.00');
        $set('Gross Amount Earned',           '35947.00');
        $set('Hazard Pay',                    '5000.00');
        $set('WHT Hazard Pay',                '250.00');
        $set('Subsistence and Laundry',       '4000.00');
        $set('Subsistence Allowance',         '3000.00');
        $set('Laundry Allowance',             '500.00');
        $set('Gross Amount',                  '3500.00');
        $set('Late/Absent/Work at Home/On Quarantine', '125.00');
        $set('OB/Travel/Seminar with meals',  '0.00');
        $set('Amount',                        '125.00');
        $set('Net SALA',                      '3375.00');
        $set('Year-End Bonus',                '37024.00');
        $set('Cash Gift',                     '5000.00');
        $set('Less: BIR Tax Withheld',        '0.00');
        $set('NET AMOUNT',                    '42024.00');
        $set('Gross',                         '6000.00');
        $set('Deductions',                    '0.00');
        $set('Net',                           '6000.00');
        $set('Longevity Pay',                 '3000.00');
        $set('Longevity Pay Tax',             '150.00');
        $set('Others (Bonus/Incentives/CA)',  '5000.00');
        $set('Total Deductions',              '12000.00');
        $set('Net Amount Due',                '23947.00');
        $set('First Half',                    '12000.00');
        $set('Second Half',                   '11947.00');

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);
        fputcsv($out, $example);
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);
        return $content;
    }

    /**
     * Parse a CSV payroll file (base64-encoded data URI).
     * Returns batch metadata + item rows.
     */
    public function parseMain(string $base64, array $overrideMeta = []): array
    {
        $items = $this->parseCsvData($base64);

        $meta = array_merge([
            'payroll_no'   => '',
            'fund_cluster' => '',
            'entity_name'  => 'Philippine Science High School — Caraga Region Campus',
            'period_start' => date('Y-m-01'),
            'period_end'   => date('Y-m-t'),
            'month'        => (int) date('n'),
            'year'         => (int) date('Y'),
        ], array_filter($overrideMeta, fn($v) => $v !== null && $v !== ''));

        return array_merge($meta, ['items' => $items]);
    }

    public function normalizeName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = preg_replace('/\.\s*$/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    // ── CSV parsing ───────────────────────────────────────────────────────────

    private function parseCsvData(string $data): array
    {
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Could not decode the uploaded file. Make sure it is a valid CSV.');
        }

        // Reject binary files (xlsx/xls)
        $magic = substr($decoded, 0, 4);
        if ($magic === "PK\x03\x04" || $magic === "\xD0\xCF\x11\xE0") {
            throw new \InvalidArgumentException('Excel files are no longer supported. Please upload a CSV file.');
        }

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $decoded)));
        $lines = array_values($lines);

        if (empty($lines)) return [];

        $headers = array_map('trim', str_getcsv(array_shift($lines)));

        // Build column index
        $colMap = [];
        foreach ($headers as $idx => $h) {
            if (isset(self::CSV_COLUMNS[$h])) {
                $colMap[$idx] = self::CSV_COLUMNS[$h];
            }
        }

        // Find which column holds the employee name (handles NO. as column 0)
        $nameColIdx = array_search('Name', $headers);
        if ($nameColIdx === false) $nameColIdx = 0;

        $stringFields = ['employee_name_raw', 'employee_no', 'position'];
        $items  = [];
        $rowNum = 2;

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $cells = str_getcsv($line);

            $name = trim($cells[$nameColIdx] ?? '');
            if ($name === '' || $this->isFooter($name)) continue;

            $row = ['excel_row_number' => $rowNum, 'raw_row_json' => []];
            foreach ($colMap as $idx => $field) {
                $val       = trim($cells[$idx] ?? '');
                $row[$field] = in_array($field, $stringFields)
                    ? $val
                    : (is_numeric(str_replace(',', '', $val)) ? (float) str_replace(',', '', $val) : 0.0);
            }

            if (empty($row['employee_name_raw'])) $row['employee_name_raw'] = $name;

            // Auto-compute sala (gross SALA) from subsistence + laundry if blank
            if (empty($row['sala'])) {
                $row['sala'] = (float)($row['subsistence_allowance'] ?? 0) + (float)($row['laundry_allowance'] ?? 0);
            }

            // Auto-compute gross_earnings if blank
            if (empty($row['gross_earnings'])) {
                $row['gross_earnings'] = array_sum(array_map(
                    fn($f) => (float) ($row[$f] ?? 0),
                    ['basic_salary', 'pera', 'salary_increase', 'additional_compensation',
                     'sala', 'hazard_pay', 'longevity_pay', 'others_bonuses',
                     'year_end_bonus', 'cash_gift', 'clothing_allowance']
                ));
            }

            // Auto-compute total_deductions if blank
            if (empty($row['total_deductions'])) {
                $row['total_deductions'] = array_sum(array_map(
                    fn($f) => (float) ($row[$f] ?? 0),
                    ['lawop', 'ob_travel_seminar', 'bir_tax', 'wht_hazard', 'longevity_tax',
                     'gsis_contribution', 'gsis_salary_loan', 'gsis_policy_loan',
                     'gsis_consolidated_loan', 'gsis_emergency_loan', 'gsis_mpl',
                     'gsis_gfal', 'gsis_cpl', 'gsis_mpl_lite',
                     'hdmf_contribution', 'hdmf_salary_loan', 'hdmf_calamity',
                     'hdmf_housing', 'hdmf_multipurpose', 'hdmf_mp2',
                     'landbank_loan', 'philhealth_contribution']
                ));
            }

            // Auto-compute net_pay if blank
            if (empty($row['net_pay'])) {
                $row['net_pay'] = ($row['gross_earnings'] ?? 0) - ($row['total_deductions'] ?? 0);
            }

            $items[] = $row;
            $rowNum++;
        }

        return $items;
    }

    private function isFooter(string $name): bool
    {
        $lower = strtolower(trim($name));
        foreach (self::FOOTER_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) return true;
        }
        return false;
    }
}
