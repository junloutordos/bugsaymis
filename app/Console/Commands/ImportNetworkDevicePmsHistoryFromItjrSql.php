<?php

namespace App\Console\Commands;

use App\Models\ICTEquipment;
use App\Models\ICTPMSHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportNetworkDevicePmsHistoryFromItjrSql extends Command
{
    protected $signature = 'ict:import-network-device-pms-history
        {--sql= : Path to the legacy itjr.sql dump}
        {--payload-base64= : Base64-encoded JSON rows extracted from the dump}
        {--execute : Insert records instead of dry-running}';

    protected $description = 'Import legacy ITJR PMS/history rows for imported network devices/access points';

    private const NETWORK_DEVICE_TYPE = 11;
    private const ACCESS_POINT_TYPE = 17;

    public function handle(): int
    {
        $rows = $this->loadRows();
        if (empty($rows)) {
            $this->error('No legacy PMS history rows found for network devices/access points.');
            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $prepared = $this->prepareRows($rows);

        $this->report($prepared, $execute);

        if (! $execute) {
            $this->warn('Dry run only. Re-run with --execute to insert.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($prepared) {
            foreach ($prepared['insertable'] as $item) {
                ICTPMSHistory::create($item['attributes']);
            }
        });

        $this->info('Inserted ' . count($prepared['insertable']) . ' PMS history record(s).');
        return self::SUCCESS;
    }

    private function loadRows(): array
    {
        if ($payload = $this->option('payload-base64')) {
            $json = base64_decode($payload, true);
            if ($json === false) {
                $this->error('Invalid base64 payload.');
                return [];
            }

            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                $this->error('Invalid JSON payload.');
                return [];
            }

            return $decoded;
        }

        $path = $this->option('sql');
        if (! $path || ! is_file($path)) {
            $this->error('Provide a readable --sql path or --payload-base64.');
            return [];
        }

        $sql = file_get_contents($path);
        $legacyEquipmentIds = $this->extractNetworkEquipmentIds($sql);

        preg_match_all('/INSERT INTO `ict_history` .*? VALUES\s*(.*?);/s', $sql, $matches);

        $rows = [];
        foreach ($matches[1] as $block) {
            foreach ($this->parseTuples($block) as $tuple) {
                $legacyEquipmentId = (string) ($tuple[1] ?? '');
                if (! isset($legacyEquipmentIds[$legacyEquipmentId])) {
                    continue;
                }

                $rows[] = [
                    'legacy_history_id'   => $this->nullIfBlank($tuple[0] ?? null),
                    'legacy_equipment_id' => $this->nullIfBlank($tuple[1] ?? null),
                    'type'                => $this->nullIfBlank($tuple[2] ?? null),
                    'particulars'         => $this->nullIfBlank($tuple[3] ?? null),
                    'date_attended'       => $this->nullIfBlank($tuple[4] ?? null),
                    'attendedby'          => $this->nullIfBlank($tuple[5] ?? null),
                    'remarks'             => $this->nullIfBlank($tuple[6] ?? null),
                ];
            }
        }

        return $rows;
    }

    private function extractNetworkEquipmentIds(string $sql): array
    {
        preg_match_all('/INSERT INTO `ict_equipment` .*? VALUES\s*(.*?);/s', $sql, $matches);

        $ids = [];
        foreach ($matches[1] as $block) {
            foreach ($this->parseTuples($block) as $tuple) {
                if (in_array((int) ($tuple[2] ?? 0), [self::NETWORK_DEVICE_TYPE, self::ACCESS_POINT_TYPE], true)) {
                    $ids[(string) ($tuple[0] ?? '')] = true;
                }
            }
        }

        return $ids;
    }

    private function prepareRows(array $rows): array
    {
        $equipmentByLegacyId = $this->equipmentByLegacyId();
        $usersByEmail = User::all(['id', 'email'])
            ->filter(fn (User $user) => filled($user->email))
            ->keyBy(fn (User $user) => strtolower($user->email));

        $insertable = [];
        $duplicates = [];
        $missingEquipment = [];
        $missingUsers = [];
        $badDates = [];

        foreach ($rows as $row) {
            $legacyHistoryId = (string) ($row['legacy_history_id'] ?? '');
            $legacyEquipmentId = (string) ($row['legacy_equipment_id'] ?? '');
            $equipment = $equipmentByLegacyId[$legacyEquipmentId] ?? null;

            if (! $equipment) {
                $missingEquipment[$legacyEquipmentId] = true;
                continue;
            }

            if ($this->isDuplicate($legacyHistoryId)) {
                $duplicates[] = $row;
                continue;
            }

            $date = $this->parseDate($row['date_attended'] ?? null);
            if (($row['date_attended'] ?? null) && ! $date) {
                $badDates[] = $row;
            }

            $attendedBy = strtolower((string) ($row['attendedby'] ?? ''));
            $creator = $attendedBy ? ($usersByEmail[$attendedBy] ?? null) : null;
            if ($attendedBy && ! $creator) {
                $missingUsers[$attendedBy] = true;
            }

            $remarks = collect([
                $this->nullIfBlank($row['remarks'] ?? null),
                'Legacy ITJR history ID: ' . ($legacyHistoryId ?: 'unknown'),
                'Legacy ITJR equipment ID: ' . ($legacyEquipmentId ?: 'unknown'),
                'Legacy type: ' . (($row['type'] ?? null) ?: 'Unknown'),
                $attendedBy ? 'Legacy attended by: ' . $attendedBy : null,
                ($row['date_attended'] ?? null) && $date !== ($row['date_attended'] ?? null)
                    ? 'Legacy date value: ' . $row['date_attended']
                    : null,
            ])->filter()->implode("\n");

            $insertable[] = [
                'source' => $row,
                'equipment' => $equipment,
                'attributes' => [
                    'ict_pms_id'     => null,
                    'equipment_id'   => $equipment->id,
                    'pms_date'       => $date,
                    'description'    => $this->nullIfBlank($row['particulars'] ?? null),
                    'type'           => $this->mapType($row['type'] ?? null),
                    'cost_of_repair' => 0,
                    'remarks'        => $remarks ?: null,
                    'created_by'     => $creator?->id,
                ],
            ];
        }

        return [
            'source_count'       => count($rows),
            'insertable'         => $insertable,
            'duplicates'         => $duplicates,
            'missing_equipment'  => array_keys($missingEquipment),
            'missing_users'      => array_keys($missingUsers),
            'bad_dates'          => $badDates,
        ];
    }

    private function equipmentByLegacyId()
    {
        return ICTEquipment::query()
            ->where('remarks', 'like', '%Legacy ITJR equipment ID:%')
            ->get(['id', 'description', 'serial_no', 'remarks'])
            ->mapWithKeys(function (ICTEquipment $equipment) {
                if (preg_match('/Legacy ITJR equipment ID:\s*(\d+)/', (string) $equipment->remarks, $match)) {
                    return [$match[1] => $equipment];
                }

                return [];
            });
    }

    private function report(array $prepared, bool $execute): void
    {
        $this->line($execute ? 'Mode: EXECUTE' : 'Mode: DRY RUN');
        $this->line('Source PMS history rows: ' . $prepared['source_count']);
        $this->line('Insertable rows: ' . count($prepared['insertable']));
        $this->line('Skipped duplicates: ' . count($prepared['duplicates']));

        if (! empty($prepared['missing_equipment'])) {
            $this->warn('Missing imported equipment for legacy IDs:');
            foreach ($prepared['missing_equipment'] as $legacyId) {
                $this->line("  {$legacyId}");
            }
        }

        if (! empty($prepared['missing_users'])) {
            $this->warn('Missing attended-by users; created_by will be blank:');
            foreach ($prepared['missing_users'] as $email) {
                $this->line("  {$email}");
            }
        }

        if (! empty($prepared['bad_dates'])) {
            $this->warn('Rows with invalid dates; pms_date will be blank unless corrected by importer:');
            foreach ($prepared['bad_dates'] as $row) {
                $this->line('  History ' . ($row['legacy_history_id'] ?? 'unknown') . ': ' . ($row['date_attended'] ?? ''));
            }
        }

        $this->table(
            ['History ID', 'Legacy Eq ID', 'Equipment ID', 'Date', 'Type', 'Created By'],
            collect($prepared['insertable'])->take(15)->map(fn ($item) => [
                $item['source']['legacy_history_id'] ?? '',
                $item['source']['legacy_equipment_id'] ?? '',
                $item['attributes']['equipment_id'],
                $item['attributes']['pms_date'] ?? '',
                $item['attributes']['type'],
                $item['attributes']['created_by'] ?? '',
            ])->all()
        );
    }

    private function isDuplicate(string $legacyHistoryId): bool
    {
        if ($legacyHistoryId === '') {
            return false;
        }

        return ICTPMSHistory::withTrashed()
            ->where('remarks', 'like', '%Legacy ITJR history ID: ' . $legacyHistoryId . '%')
            ->exists();
    }

    private function parseDate(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (preg_match('/^20204-(\d{2})-(\d{2})$/', $value, $match)) {
            return "2024-{$match[1]}-{$match[2]}";
        }

        if (! preg_match('/^(19|20)\d{2}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapType(?string $type): string
    {
        return match ($type) {
            'Hardware Repair' => 'Repair',
            default => 'PMS',
        };
    }

    private function parseTuples(string $block): array
    {
        $rows = [];
        $current = [];
        $value = '';
        $inString = false;
        $escaped = false;
        $depth = 0;

        for ($i = 0, $length = strlen($block); $i < $length; $i++) {
            $char = $block[$i];

            if ($inString) {
                if ($escaped) {
                    $value .= $char;
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === "'") {
                    $inString = false;
                    continue;
                }
                $value .= $char;
                continue;
            }

            if ($char === "'") {
                $inString = true;
                continue;
            }

            if ($char === '(') {
                $depth = 1;
                $current = [];
                $value = '';
                continue;
            }

            if ($depth === 0) {
                continue;
            }

            if ($char === ',') {
                $current[] = $this->nullIfBlank($value);
                $value = '';
                continue;
            }

            if ($char === ')') {
                $current[] = $this->nullIfBlank($value);
                $rows[] = $current;
                $depth = 0;
                $value = '';
                continue;
            }

            $value .= $char;
        }

        return $rows;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' || strtoupper($value) === 'NULL' ? null : $value;
    }
}
