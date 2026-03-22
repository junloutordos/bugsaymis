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
     * Parse a ZKTeco .dat / .txt biometric export file and insert into biometric_logs.
     *
     * Supported formats:
     *   Format A (5+ cols, date and time split):
     *     employee_id \t YYYY-MM-DD \t HH:MM:SS \t log_code \t device_id
     *
     *   Format B (4+ cols, datetime combined):
     *     employee_id \t YYYY-MM-DD HH:MM:SS \t log_code \t 0 \t device_id
     *
     * Log codes: 0=time_in, 1=time_out, 4=time_in(OT), 5=time_out(OT), else=auto
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

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! ctype_digit($line[0])) {
                // Skip blank or header lines
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

    private function parseLine(string $line, string $importBatch, ?string $deviceId, array $userMap): ?array
    {
        $parts = array_map('trim', explode("\t", $line));

        if (count($parts) < 3) {
            return null;
        }

        $deviceEmployeeId = $parts[0];
        $logDatetime      = null;
        $logCode          = 0;
        $deviceCol        = $deviceId;

        // Detect format
        if (count($parts) >= 5 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[1])) {
            // Format A: emp_id | date | time | log_code | device_id
            $logDatetime = $parts[1] . ' ' . $parts[2];
            $logCode     = (int) $parts[3];
            $deviceCol   = $deviceId ?? ($parts[4] ?? null);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $parts[1])) {
            // Format B: emp_id | datetime | log_code | 0 | device_id
            $logDatetime = $parts[1];
            $logCode     = (int) ($parts[2] ?? 0);
            $deviceCol   = $deviceId ?? ($parts[4] ?? null);
        } else {
            return null;
        }

        // Validate datetime
        try {
            $dt = Carbon::parse($logDatetime);
        } catch (\Throwable) {
            return null;
        }

        $logType = match ($logCode) {
            0, 4    => 'time_in',
            1, 5    => 'time_out',
            default => 'auto',
        };

        $userId  = $userMap[$deviceEmployeeId] ?? null;

        return [
            'device_employee_id' => $deviceEmployeeId,
            'user_id'            => $userId,
            'device_id'          => $deviceCol,
            'log_datetime'       => $dt->format('Y-m-d H:i:s'),
            'log_type'           => $logType,
            'source'             => 'biometric',
            'is_resolved'        => $userId !== null,
            'is_duplicate'       => false,
            'import_batch'       => $importBatch,
            'imported_at'        => now()->format('Y-m-d H:i:s'),
            'created_at'         => now()->format('Y-m-d H:i:s'),
            'updated_at'         => now()->format('Y-m-d H:i:s'),
        ];
    }

    private function batchInsert(array $rows): void
    {
        // Build lookup of existing records for duplicate detection
        $keys = array_map(
            fn ($r) => $r['device_employee_id'] . '|' . $r['log_datetime'] . '|' . ($r['device_id'] ?? ''),
            $rows
        );

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
            ->pluck('log_datetime', 'device_employee_id')
            ->toArray();

        $toInsert = [];

        foreach ($rows as $row) {
            $dupKey = $row['device_employee_id'] . '|' . $row['log_datetime'] . '|' . ($row['device_id'] ?? '');

            // Check against DB existing
            $isDup = isset($existing[$row['device_employee_id']]);

            if ($isDup) {
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

    /**
     * Resolve all biometric logs for a given device_employee_id to a user.
     */
    public function resolveByDeviceId(string $deviceEmployeeId, int $userId): int
    {
        return BiometricLog::where('device_employee_id', $deviceEmployeeId)
            ->where('is_resolved', false)
            ->update(['user_id' => $userId, 'is_resolved' => true]);
    }
}
