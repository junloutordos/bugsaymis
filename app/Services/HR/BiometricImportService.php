<?php

namespace App\Services\HR;

use App\Models\HR\BiometricLog;
use App\Models\User;
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
        'new_rows'   => [],
    ];

    public function __construct(private readonly AttlogLineParser $parser)
    {
    }

    /**
     * Parse a Granding biometric .dat export file (manual upload path).
     */
    public function parse(string $filePath, string $importBatch, ?string $deviceId = null): array
    {
        if (! file_exists($filePath)) {
            Log::warning('BiometricImportService: file not found', ['path' => $filePath]);

            return $this->resetStats();
        }

        $text = file_get_contents($filePath);

        Log::info('BiometricImport sample lines', [
            'lines' => array_slice(preg_split('/\r\n|\r|\n/', $text) ?: [], 0, 3),
            'batch' => $importBatch,
        ]);

        return $this->ingest($text, $importBatch, $deviceId, 'biometric');
    }

    /**
     * Ingest punches relayed live by an Atlas Sentinel biometric bridge.
     */
    public function ingestApiPunches(string $rawBody, string $deviceKey): array
    {
        return $this->ingest($rawBody, null, $deviceKey, 'api');
    }

    private function ingest(string $text, ?string $importBatch, ?string $deviceId, string $source): array
    {
        $this->resetStats();

        $userMap = User::whereNotNull('badge_id')
            ->pluck('id', 'badge_id')
            ->toArray();

        $parsed = $this->parser->parseText($text);
        $this->stats['skipped'] = $parsed['skipped'];

        $rows = [];
        foreach ($parsed['rows'] as $p) {
            $userId = $userMap[$p['device_employee_id']] ?? null;

            $rows[] = [
                'device_employee_id' => $p['device_employee_id'],
                'user_id'            => $userId,
                'device_id'          => $deviceId,
                'log_datetime'       => $p['log_datetime'],
                'log_type'           => $p['log_type'],
                'source'             => $source,
                'is_resolved'        => $userId !== null,
                'is_duplicate'       => false,
                'import_batch'       => $importBatch,
                'imported_at'        => now()->format('Y-m-d H:i:s'),
                'created_at'         => now()->format('Y-m-d H:i:s'),
                'updated_at'         => now()->format('Y-m-d H:i:s'),
            ];

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

    private function resetStats(): array
    {
        return $this->stats = [
            'inserted'   => 0,
            'resolved'   => 0,
            'unresolved' => 0,
            'duplicates' => 0,
            'skipped'    => 0,
            'new_rows'   => [],
        ];
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
            $this->stats['new_rows'] = array_merge($this->stats['new_rows'], $toInsert);
        }
    }

    public function resolveByDeviceId(string $deviceEmployeeId, int $userId): int
    {
        return BiometricLog::where('device_employee_id', $deviceEmployeeId)
            ->where('is_resolved', false)
            ->update(['user_id' => $userId, 'is_resolved' => true]);
    }
}
