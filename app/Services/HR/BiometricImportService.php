<?php

namespace App\Services\HR;

use App\Models\HR\BiometricLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiometricImportService
{
    private array $stats = [
        'inserted'   => 0,
        'resolved'   => 0,
        'unresolved' => 0,
        'duplicates' => 0,
        'skipped'    => 0,
    ];

    /**
     * Parse a Granding biometric .dat export file.
     *
     * Confirmed format:
     *   Column 1 — badge / employee number
     *   Column 2 — date and time  (YYYY-MM-DD HH:MM:SS  or  YYYY/MM/DD HH:MM:SS)
     *   Column 3 — check-type code (optional)
     *
     * Delimiter: tab or any whitespace. When single-space-delimited, the date
     * and time arrive as two separate tokens — the parser re-joins them.
     *
     * check_type codes:
     *   0 / "I"  = Check In        1 / "O"  = Check Out
     *   2 / "OO" = Break Out        3 / "OI" = Break In
     *   4        = Overtime In      5        = Overtime Out
     */
    public function parse(string $filePath, string $importBatch, ?string $deviceId = null): array
    {
        $this->stats = ['inserted' => 0, 'resolved' => 0, 'unresolved' => 0, 'duplicates' => 0, 'skipped' => 0];

        if (! file_exists($filePath)) {
            Log::warning('BiometricImportService: file not found', ['path' => $filePath]);
            return $this->stats;
        }

        // Pre-load badge_id → user_id map for fast resolution
        $userMap = User::whereNotNull('badge_id')
            ->pluck('id', 'badge_id')
            ->toArray();

        $rows  = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Log first few raw lines to help debug format issues
        $sampleLines = array_slice($lines, 0, 3);
        Log::info('BiometricImport sample lines', ['lines' => $sampleLines, 'batch' => $importBatch]);

        foreach ($lines as $lineRaw) {
            // Strip BOM (UTF-8 BOM: EF BB BF) and trim
            $line = ltrim($lineRaw, "\xEF\xBB\xBF");
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Skip header rows — common in Granding CAMS exports
            if ($this->isHeaderLine($line)) {
                continue;
            }

            $parsed = $this->parseLine($line, $importBatch, $deviceId, $userMap);
            if ($parsed === null) {
                $this->stats['skipped']++;
                continue;
            }

            $rows[] = $parsed;

            if (count($rows) >= 500) {
                $this->batchInsert($rows);
                $rows = [];
            }
        }

        if (! empty($rows)) {
            $this->batchInsert($rows);
        }

        return $this->stats;
    }

    /**
     * Detect header / non-data lines common in Granding exports.
     */
    private function isHeaderLine(string $line): bool
    {
        $lower = strtolower($line);

        // Granding CAMS headers
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

    private function parseLine(string $line, string $importBatch, ?string $deviceId, array $userMap): ?array
    {
        // ── Step 1: split on tabs only (preserves the datetime's internal space) ─
        $fields = array_map('trim', explode("\t", $line));

        // Need at least: badge | datetime | ... | check_type
        if (count($fields) < 2) {
            return null;
        }

        $badgeNo  = $fields[0];
        $dtRaw    = str_replace('/', '-', $fields[1]);  // normalise YYYY/MM/DD → YYYY-MM-DD
        $rawLogCode = null;

        // ── Step 2: parse the datetime field ────────────────────────────────────
        // Expected: "YYYY-MM-DD HH:MM:SS"  or  "YYYY-MM-DD HH:MM"
        if (! preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}(?::\d{2})?)$/', $dtRaw, $dtMatch)) {
            return null;
        }
        $logDatetime = $dtMatch[1] . ' ' . $dtMatch[2];

        // ── Step 3: locate check type ────────────────────────────────────────────
        // Granding attlog format:
        //   [badge] [datetime] [verify_type=1] [io_status=0/1] [work_code] [reserved]
        //
        // io_status (fields[3]) is the check type: 0=Check-In, 1=Check-Out
        if (isset($fields[3]) && $fields[3] !== '') {
            $rawLogCode = $fields[3];
        } elseif (isset($fields[2]) && $fields[2] !== '') {
            $rawLogCode = $fields[2];
        } else {
            $rawLogCode = '0';
        }

        // ── Step 4: parse datetime ───────────────────────────────────────────────
        try {
            $fmt = substr_count($logDatetime, ':') === 1 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
            $dt  = Carbon::createFromFormat($fmt, $logDatetime);
            if (! $dt) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $userId = $userMap[$badgeNo] ?? null;

        return [
            'device_employee_id' => $badgeNo,
            'user_id'            => $userId,
            'device_id'          => $deviceId,
            'log_datetime'       => $dt->format('Y-m-d H:i:s'),
            'log_type'           => $this->resolveLogType($rawLogCode),
            'source'             => 'biometric',
            'is_resolved'        => $userId !== null,
            'is_duplicate'       => false,
            'import_batch'       => $importBatch,
            'imported_at'        => now()->format('Y-m-d H:i:s'),
            'created_at'         => now()->format('Y-m-d H:i:s'),
            'updated_at'         => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Map raw check-type codes (numeric or Granding letter codes) to log_type.
     *
     * Granding codes: 0/"I"=in, 1/"O"=out, 2/"OO"=break-out, 3/"OI"=break-in,
     *                 4=overtime-in, 5=overtime-out
     */
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
            return 'time_out'; // break out = leaving
        }
        if (in_array($s, ['3', 'OI', 'BI'], true)) {
            return 'time_in';  // break in = returning
        }

        return 'auto';
    }

    private function batchInsert(array $rows): void
    {
        $existing = DB::table('biometric_logs')
            ->where(function ($q) use ($rows) {
                foreach ($rows as $r) {
                    $q->orWhere(function ($q2) use ($r) {
                        $q2->where('device_employee_id', $r['device_employee_id'])
                           ->where('log_datetime', $r['log_datetime'])
                           ->where(fn ($q3) => $r['device_id']
                               ? $q3->where('device_id', $r['device_id'])
                               : $q3->whereNull('device_id'));
                    });
                }
            })
            ->select('device_employee_id', 'log_datetime')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->device_employee_id . '|' . $r->log_datetime => true])
            ->toArray();

        $toInsert = [];

        foreach ($rows as $row) {
            $dupKey = $row['device_employee_id'] . '|' . $row['log_datetime'];

            if (isset($existing[$dupKey])) {
                $this->stats['duplicates']++;
                continue;
            }

            $toInsert[] = $row;

            if ($row['is_resolved']) {
                $this->stats['resolved']++;
            } else {
                $this->stats['unresolved']++;
            }
        }

        if (! empty($toInsert)) {
            DB::table('biometric_logs')->insertOrIgnore($toInsert);
            $this->stats['inserted'] += count($toInsert);
        }
    }

    public function resolveByDeviceId(string $deviceEmployeeId, int $userId): int
    {
        return BiometricLog::where('device_employee_id', $deviceEmployeeId)
            ->where('is_resolved', false)
            ->update(['user_id' => $userId, 'is_resolved' => true]);
    }
}
