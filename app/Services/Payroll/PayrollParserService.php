<?php

namespace App\Services\Payroll;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollParserService
{
    private const DATA_START_ROW = 11;

    private const MONTH_MAP = [
        'JANUARY' => 1, 'FEBRUARY' => 2, 'MARCH'  => 3,
        'APRIL'   => 4, 'MAY'      => 5, 'JUNE'   => 6,
        'JULY'    => 7, 'AUGUST'   => 8, 'SEPTEMBER' => 9,
        'OCTOBER' => 10,'NOVEMBER' => 11,'DECEMBER'  => 12,
    ];

    private const FOOTER_PATTERNS = [
        'prepared by', 'certified correct', 'total', 'sub-total',
        'job order', 'cos', 'grand total', 'noted by',
    ];

    // CSV column header → DB field map (order matches the official Excel payroll layout)
    private const CSV_COLUMNS = [
        // Identity
        'Name'                    => 'employee_name_raw',
        'Employee No.'            => 'employee_no',
        'Position'                => 'position',
        // Compensations
        'Salaries and Wages-Regular' => 'basic_salary',
        'PERA'                    => 'pera',
        'Gross Amount Earned'     => 'gross_earnings',
        // Deductions — General
        'Tardiness/AWOP'          => 'lawop',
        'BIR Withholding Tax'     => 'bir_tax',
        // GSIS
        'RLIP EE'                 => 'gsis_contribution',
        'Pol. Loan'               => 'gsis_policy_loan',
        'Emergency/Cal. Loan'     => 'gsis_emergency_loan',
        'GFAL'                    => 'gsis_gfal',
        'GSIS MPL'                => 'gsis_mpl',
        'CPL'                     => 'gsis_cpl',
        'Multi-purpose Life'      => 'gsis_mpl_lite',
        // PAG-IBIG / HDMF
        'EE Share'                => 'hdmf_contribution',
        'MP2'                     => 'hdmf_mp2',
        'PAG-IBIG MPL'            => 'hdmf_multipurpose',
        'Calamity Loan'           => 'hdmf_calamity',
        'HLF'                     => 'hdmf_housing',
        // Other deductions
        'Landbank'                => 'landbank_loan',
        'PHIC EE Share'           => 'philhealth_contribution',
        // Totals & net pay
        'Total Deductions'        => 'total_deductions',
        'Net Amount Due'          => 'net_pay',
        'First Half'              => 'first_half_amount',
        'Second Half'             => 'second_half_amount',
    ];

    /**
     * Generate and return CSV template content as a string.
     */
    public function csvTemplate(): string
    {
        $headers = array_keys(self::CSV_COLUMNS);
        $example = array_fill(0, count($headers), '');
        // Identity columns
        $example[0] = 'DELA CRUZ, Juan A.';
        $example[1] = 'pshscrc13-00123';
        $example[2] = 'Administrative Assistant II';
        // Compensations — fill with sample numeric values
        $example[3] = '33947.00'; // Salaries and Wages-Regular
        $example[4] = '2000.00';  // PERA
        $example[5] = '35947.00'; // Gross Amount Earned (auto-computed if 0)

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);
        fputcsv($out, $example);
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);
        return $content;
    }

    /**
     * Parse main payroll Excel and return batch metadata + item rows.
     *
     * @return array{ payroll_no, fund_cluster, entity_name, period_start, period_end,
     *                month, year, items: array }
     */
    public function parseMain(string $base64OrPath, string $sheetName = null, array $overrideMeta = []): array
    {
        // Detect CSV by content before trying PhpSpreadsheet
        if ($this->isCsvData($base64OrPath)) {
            $items = $this->parseCsvData($base64OrPath);
            $meta  = array_merge([
                'payroll_no'   => '',
                'fund_cluster' => '',
                'entity_name'  => 'Philippine Science High School — Caraga Region Campus',
                'period_start' => date('Y-m-01'),
                'period_end'   => date('Y-m-t'),
                'month'        => (int) date('n'),
                'year'         => (int) date('Y'),
            ], $overrideMeta);

            return array_merge($meta, ['items' => $items]);
        }

        $spreadsheet = $this->loadSpreadsheet($base64OrPath);
        $ws          = $this->resolveSheet($spreadsheet, $sheetName);

        $meta  = $this->parseMeta($ws);
        $items = $this->parseDataRows($ws);

        // Allow caller to override parsed metadata (e.g. from form fields)
        $meta = array_merge($meta, array_filter($overrideMeta, fn($v) => $v !== null && $v !== ''));

        return array_merge($meta, ['items' => $items]);
    }

    /**
     * Parse the bonus/SALA supplementary file.
     * Returns a keyed array: normalized_name => bonus_fields array.
     */
    public function parseBonus(string $base64OrPath): array
    {
        $spreadsheet = $this->loadSpreadsheet($base64OrPath);
        $ws          = $spreadsheet->getActiveSheet();

        $result = [];

        // Header is row 1 (column labels), data starts at row 2
        // Expected columns: No, Name, SALA, HAZARD, SALARY, CA, LONGEVITY, REIM, OTHERS, BONUS, PEI, Total
        $headers = [];
        foreach ($ws->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = strtoupper(trim((string) $cell->getValue()));
            }
        }

        $colMap = $this->mapBonusHeaders($headers);

        foreach ($ws->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            $rawName = isset($colMap['NAME']) ? ($cells[$colMap['NAME']] ?? null) : null;
            if (empty($rawName) || $this->isFooter((string) $rawName)) {
                continue;
            }

            $key = $this->normalizeName((string) $rawName);
            $result[$key] = [
                'sala'           => $this->num($cells, $colMap, 'SALA'),
                'hazard_pay'     => $this->num($cells, $colMap, 'HAZARD'),
                'salary_increase'=> $this->num($cells, $colMap, 'SALARY'),
                'additional_compensation' => $this->num($cells, $colMap, 'CA'),
                'longevity_pay'  => $this->num($cells, $colMap, 'LONGEVITY'),
                'others_bonuses' => $this->num($cells, $colMap, 'OTHERS')
                                  + $this->num($cells, $colMap, 'BONUS')
                                  + $this->num($cells, $colMap, 'PEI')
                                  + $this->num($cells, $colMap, 'REIM'),
            ];
        }

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function loadSpreadsheet(string $data): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        // Strip data URI prefix if present — after this $data is raw base64
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }

        // Short string starting with / or . → treat as file path
        if (strlen($data) < 4096 && (str_starts_with($data, '/') || str_starts_with($data, '.')) && file_exists($data)) {
            return IOFactory::load($data);
        }

        // Otherwise decode base64 content
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 data — could not decode file.');
        }

        // Detect .xls (legacy) vs .xlsx by magic bytes
        $magic = substr($decoded, 0, 4);
        $ext   = ($magic === "\xD0\xCF\x11\xE0") ? 'xls' : 'xlsx';
        $tmp   = sys_get_temp_dir() . '/payroll_' . uniqid() . '.' . $ext;
        file_put_contents($tmp, $decoded);
        try {
            $spreadsheet = IOFactory::load($tmp);
        } finally {
            @unlink($tmp);
        }
        return $spreadsheet;
    }

    private function resolveSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, ?string $name): Worksheet
    {
        if ($name) {
            return $spreadsheet->getSheetByName($name)
                ?? $spreadsheet->getActiveSheet();
        }

        // Auto-detect: pick the latest month sheet (e.g. "MAY (2)" wins over "MAY")
        $sheets    = $spreadsheet->getSheetNames();
        $best      = null;
        $bestMonth = 0;
        $bestRun   = 0;

        foreach ($sheets as $sheetName) {
            $upper = strtoupper(trim($sheetName));
            preg_match('/^([A-Z]+)(?:\s*\((\d+)\))?$/', $upper, $m);
            $base  = $m[1] ?? '';
            $run   = (int) ($m[2] ?? 1);
            $month = self::MONTH_MAP[$base] ?? 0;

            if ($month > $bestMonth || ($month === $bestMonth && $run > $bestRun)) {
                $bestMonth = $month;
                $bestRun   = $run;
                $best      = $sheetName;
            }
        }

        return $best
            ? $spreadsheet->getSheetByName($best)
            : $spreadsheet->getActiveSheet();
    }

    private function parseMeta(Worksheet $ws): array
    {
        $periodText  = (string) ($ws->getCell('A2')->getValue() ?? '');
        $fundCluster = $this->afterColon((string) ($ws->getCell('A4')->getValue() ?? ''));
        $entityName  = $this->afterColon((string) ($ws->getCell('A3')->getValue() ?? ''));
        $payrollNo   = $this->afterColon((string) ($ws->getCell('X4')->getValue() ?? ''));

        [$month, $year, $start, $end] = $this->parsePeriod($periodText);

        return [
            'payroll_no'   => $payrollNo,
            'fund_cluster' => $fundCluster,
            'entity_name'  => $entityName,
            'period_start' => $start,
            'period_end'   => $end,
            'month'        => $month,
            'year'         => $year,
        ];
    }

    private function parseDataRows(Worksheet $ws): array
    {
        $items = [];

        for ($r = self::DATA_START_ROW; $r <= $ws->getHighestRow(); $r++) {
            $nameVal = $ws->getCell('B' . $r)->getValue();

            if ($nameVal === null || $nameVal === '') {
                // Allow a few blank rows between sections; stop on 3 consecutive blanks
                if (($ws->getCell('C' . $r)->getValue() === null)
                    && ($ws->getCell('D' . $r)->getValue() === null)) {
                    continue;
                }
            }

            $name = trim((string) $nameVal);

            if ($this->isFooter($name)) {
                break;
            }

            if ($this->isSectionHeader($ws, $r)) {
                continue;
            }

            $n = fn(string $col) => $this->cellNum($ws, $col . $r);

            $items[] = [
                'excel_row_number'   => $r,
                'employee_name_raw'  => $name,
                'position'           => trim((string) ($ws->getCell('C' . $r)->getValue() ?? '')),
                'basic_salary'       => $n('D'),
                'pera'               => $n('E'),
                'gross_earnings'     => $n('F'),
                'lawop'              => $n('G'),
                'bir_tax'            => $n('H'),
                'gsis_contribution'  => $n('I'),
                'gsis_policy_loan'   => $n('J'),
                'gsis_emergency_loan'=> $n('K'),
                'gsis_gfal'          => $n('L'),
                'gsis_consolidated_loan' => $n('M'),
                'gsis_mpl'           => $n('N'),
                'gsis_cpl'           => $n('O'),
                'gsis_mpl_lite'      => $n('P'),
                'hdmf_contribution'  => $n('Q'),
                'hdmf_mp2'           => $n('R'),
                'hdmf_multipurpose'  => $n('S'),
                'hdmf_calamity'      => $n('T'),
                'hdmf_housing'       => $n('U'),
                'landbank_loan'      => $n('V'),
                'philhealth_contribution' => $n('W'),
                'total_deductions'   => $n('X'),
                'net_pay'            => $n('Y'),
                'first_half_amount'  => $n('Z'),
                'second_half_amount' => $n('AA'),
                'raw_row_json'       => $this->dumpRow($ws, $r),
            ];
        }

        return $items;
    }

    private function isSectionHeader(Worksheet $ws, int $row): bool
    {
        // A section header row has a name in B but all numeric columns are 0/null
        $hasNumeric = false;
        foreach (['D', 'E', 'F', 'Y'] as $col) {
            $v = $ws->getCell($col . $row)->getValue();
            if (is_numeric($v) && (float) $v !== 0.0) {
                $hasNumeric = true;
                break;
            }
        }
        return !$hasNumeric;
    }

    private function isFooter(string $name): bool
    {
        $lower = strtolower(trim($name));
        foreach (self::FOOTER_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function cellNum(Worksheet $ws, string $cellRef): float
    {
        $v = $ws->getCell($cellRef)->getValue();
        if (is_numeric($v)) return (float) $v;
        if (is_string($v)) {
            $clean = str_replace([',', ' '], '', $v);
            return is_numeric($clean) ? (float) $clean : 0.0;
        }
        return 0.0;
    }

    private function dumpRow(Worksheet $ws, int $row): array
    {
        $data = [];
        foreach (range('A', 'Z') as $col) {
            $data[$col] = $ws->getCell($col . $row)->getValue();
        }
        $data['AA'] = $ws->getCell('AA' . $row)->getValue();
        return $data;
    }

    private function parsePeriod(string $text): array
    {
        // "For the period MAY 1-31, 2026"
        if (!preg_match('/([A-Z]+)\s+(\d{1,2})\s*[-\x{2013}]\s*(\d{1,2}),\s*(\d{4})/iu', $text, $m)) {
            return [1, date('Y'), date('Y-m-01'), date('Y-m-t')];
        }

        $monthName = strtoupper(trim($m[1]));
        $d1        = (int) $m[2];
        $d2        = (int) $m[3];
        $year      = (int) $m[4];
        $month     = self::MONTH_MAP[$monthName] ?? 1;

        return [
            $month,
            $year,
            sprintf('%04d-%02d-%02d', $year, $month, $d1),
            sprintf('%04d-%02d-%02d', $year, $month, $d2),
        ];
    }

    private function afterColon(string $value): string
    {
        $pos = strpos($value, ':');
        return $pos !== false ? trim(substr($value, $pos + 1)) : trim($value);
    }

    private function mapBonusHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $map[$h] = $idx;
        }
        return $map;
    }

    private function num(array $cells, array $colMap, string $key): float
    {
        if (!isset($colMap[$key])) return 0.0;
        $v = $cells[$colMap[$key]] ?? null;
        if (is_numeric($v)) return (float) $v;
        return 0.0;
    }

    public function normalizeName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = preg_replace('/\.\s*$/', '', $name); // trailing period
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    // ── CSV helpers ───────────────────────────────────────────────────────────

    private function isCsvData(string $data): bool
    {
        // Strip data URI prefix
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $decoded = base64_decode($data, true);
        if ($decoded === false) return false;

        // CSV has no binary magic bytes; xlsx starts with PK (zip), xls with D0CF
        $magic = substr($decoded, 0, 4);
        if ($magic === "PK\x03\x04" || $magic === "\xD0\xCF\x11\xE0") return false;

        // Looks like text — check first line has commas
        $firstLine = strtok($decoded, "\n");
        return str_contains($firstLine, ',');
    }

    private function parseCsvData(string $data): array
    {
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $decoded = base64_decode($data, true);
        $lines   = array_filter(explode("\n", str_replace("\r\n", "\n", $decoded)));
        $lines   = array_values($lines);

        if (empty($lines)) return [];

        // Parse header row
        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('trim', $headers);

        // Build index: CSV header → DB field
        $colMap = [];
        foreach ($headers as $idx => $h) {
            if (isset(self::CSV_COLUMNS[$h])) {
                $colMap[$idx] = self::CSV_COLUMNS[$h];
            }
        }

        $items = [];
        $rowNum = 2;
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $cells = str_getcsv($line);

            $name = trim($cells[0] ?? '');
            if ($name === '' || $this->isFooter($name)) continue;

            $row = ['excel_row_number' => $rowNum, 'raw_row_json' => []];
            $stringFields = ['employee_name_raw', 'employee_no', 'position'];
            foreach ($colMap as $idx => $field) {
                $val = trim($cells[$idx] ?? '');
                $row[$field] = in_array($field, $stringFields)
                    ? $val
                    : (is_numeric(str_replace(',', '', $val)) ? (float) str_replace(',', '', $val) : 0.0);
            }

            // Ensure required field
            if (empty($row['employee_name_raw'])) $row['employee_name_raw'] = $name;

            // Auto-compute gross_earnings if blank
            if (empty($row['gross_earnings']) || $row['gross_earnings'] == 0) {
                $row['gross_earnings'] = array_sum(array_map(
                    fn($f) => $row[$f] ?? 0,
                    ['basic_salary', 'pera', 'salary_increase', 'additional_compensation',
                     'sala', 'hazard_pay', 'longevity_pay', 'others_bonuses']
                ));
            }

            // Auto-compute total_deductions if blank
            if (empty($row['total_deductions']) || $row['total_deductions'] == 0) {
                $deductionFields = ['lawop','bir_tax','gsis_contribution','gsis_salary_loan',
                    'gsis_policy_loan','gsis_consolidated_loan','gsis_emergency_loan',
                    'gsis_mpl','gsis_gfal','gsis_cpl','gsis_mpl_lite',
                    'hdmf_contribution','hdmf_salary_loan','hdmf_calamity',
                    'hdmf_housing','hdmf_multipurpose','hdmf_mp2',
                    'landbank_loan','credit_union','philhealth_contribution'];
                $row['total_deductions'] = array_sum(array_map(fn($f) => $row[$f] ?? 0, $deductionFields));
            }

            // Auto-compute net_pay if blank
            if (empty($row['net_pay']) || $row['net_pay'] == 0) {
                $row['net_pay'] = ($row['gross_earnings'] ?? 0) - ($row['total_deductions'] ?? 0);
            }

            $items[] = $row;
            $rowNum++;
        }

        return $items;
    }
}
