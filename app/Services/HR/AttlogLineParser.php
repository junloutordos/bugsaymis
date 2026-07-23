<?php

namespace App\Services\HR;

use Carbon\Carbon;

/**
 * Decodes the Granding biometric attlog text format into normalized punch
 * rows. Shared by the manual .dat file upload path
 * (BiometricImportService::parse) and the live Atlas Sentinel bridge ingest
 * path (BiometricImportService::ingestApiPunches) so both agree on the
 * exact same format knowledge.
 *
 * Confirmed format:
 *   Column 1 — badge / employee number
 *   Column 2 — date and time  (YYYY-MM-DD HH:MM:SS  or  YYYY/MM/DD HH:MM:SS)
 *   Column 3 — check-type code (optional)
 *
 * Delimiter: tab. When single-space-delimited, the date and time arrive as
 * two separate tokens — not handled here (matches prior behavior).
 *
 * check_type codes:
 *   0 / "I"  = Check In        1 / "O"  = Check Out
 *   2 / "OO" = Break Out        3 / "OI" = Break In
 *   4        = Overtime In      5        = Overtime Out
 */
class AttlogLineParser
{
    /** @return array{rows: array<int, array{device_employee_id: string, log_datetime: string, log_type: string}>, skipped: int} */
    public function parseText(string $text): array
    {
        $rows    = [];
        $skipped = 0;
        $lines   = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($lines as $lineRaw) {
            $line = ltrim($lineRaw, "\xEF\xBB\xBF");
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($this->isHeaderLine($line)) {
                continue;
            }

            $parsed = $this->parseLine($line);
            if ($parsed === null) {
                $skipped++;
                continue;
            }

            $rows[] = $parsed;
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    private function isHeaderLine(string $line): bool
    {
        $lower = strtolower($line);

        if (str_starts_with($lower, 'no.')
            || str_starts_with($lower, 'enrollment')
            || str_starts_with($lower, 'userid')
            || str_starts_with($lower, 'pin')
            || str_starts_with($lower, 'employee')
            || str_starts_with($lower, 'date')
            || str_starts_with($lower, 'name')
        ) {
            return true;
        }

        return false;
    }

    /** @return array{device_employee_id: string, log_datetime: string, log_type: string}|null */
    private function parseLine(string $line): ?array
    {
        $fields = array_map('trim', explode("\t", $line));

        if (count($fields) < 2) {
            return null;
        }

        $badgeNo = $fields[0];
        $dtRaw   = str_replace('/', '-', $fields[1]);

        if (! preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}(?::\d{2})?)$/', $dtRaw, $dtMatch)) {
            return null;
        }
        $logDatetime = $dtMatch[1] . ' ' . $dtMatch[2];

        if (isset($fields[3]) && $fields[3] !== '') {
            $rawLogCode = $fields[3];
        } elseif (isset($fields[2]) && $fields[2] !== '') {
            $rawLogCode = $fields[2];
        } else {
            $rawLogCode = '0';
        }

        try {
            $fmt = substr_count($logDatetime, ':') === 1 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
            $dt  = Carbon::createFromFormat($fmt, $logDatetime);
            if (! $dt) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return [
            'device_employee_id' => $badgeNo,
            'log_datetime'       => $dt->format('Y-m-d H:i:s'),
            'log_type'           => $this->resolveLogType($rawLogCode),
        ];
    }

    private function resolveLogType(mixed $raw): string
    {
        $s = strtoupper(trim((string) $raw));

        if (in_array($s, ['0', 'I', 'CI', 'IN', '4'], true)) {
            return 'time_in';
        }
        if (in_array($s, ['1', 'O', 'CO', 'OUT', '5'], true)) {
            return 'time_out';
        }
        if (in_array($s, ['2', 'OO', 'BO'], true)) {
            return 'time_out';
        }
        if (in_array($s, ['3', 'OI', 'BI'], true)) {
            return 'time_in';
        }

        return 'auto';
    }
}
