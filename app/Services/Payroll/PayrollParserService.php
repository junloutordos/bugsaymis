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

    /**
     * Parse main payroll Excel and return batch metadata + item rows.
     *
     * @return array{ payroll_no, fund_cluster, entity_name, period_start, period_end,
     *                month, year, items: array }
     */
    public function parseMain(string $base64OrPath, string $sheetName = null): array
    {
        $spreadsheet = $this->loadSpreadsheet($base64OrPath);
        $ws          = $this->resolveSheet($spreadsheet, $sheetName);

        $meta  = $this->parseMeta($ws);
        $items = $this->parseDataRows($ws);

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
        // Accept base64 data URI or raw base64 or a file path
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }

        if (base64_decode($data, true) !== false && !file_exists($data)) {
            $tmp = tempnam(sys_get_temp_dir(), 'payroll_') . '.xlsx';
            file_put_contents($tmp, base64_decode($data));
            $spreadsheet = IOFactory::load($tmp);
            unlink($tmp);
            return $spreadsheet;
        }

        return IOFactory::load($data);
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
}
