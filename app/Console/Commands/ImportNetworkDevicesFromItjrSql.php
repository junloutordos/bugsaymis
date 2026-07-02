<?php

namespace App\Console\Commands;

use App\Models\ICTEquipment;
use App\Models\IctEquipmentAssignmentHistory;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportNetworkDevicesFromItjrSql extends Command
{
    protected $signature = 'ict:import-network-devices
        {--sql= : Path to the legacy itjr.sql dump}
        {--payload-base64= : Base64-encoded JSON rows extracted from the dump}
        {--execute : Insert records instead of dry-running}';

    protected $description = 'Import legacy ITJR network devices/access points into ict_equipments';

    private const NETWORK_DEVICE_TYPE = 11;
    private const ACCESS_POINT_TYPE = 17;

    public function handle(): int
    {
        $rows = $this->loadRows();
        if (empty($rows)) {
            $this->error('No network device rows found.');
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
                $equipment = ICTEquipment::create($item['attributes']);

                if ($equipment->owner_id) {
                    IctEquipmentAssignmentHistory::create([
                        'equipment_id' => $equipment->id,
                        'user_id'      => $equipment->owner_id,
                        'assigned_at'  => now(),
                        'reason'       => 'Imported from legacy ITJR network device inventory.',
                    ]);
                }
            }
        });

        $this->info('Inserted ' . count($prepared['insertable']) . ' network device record(s).');
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
        preg_match_all('/INSERT INTO `ict_equipment` .*? VALUES\s*(.*?);/s', $sql, $matches);

        $rows = [];
        foreach ($matches[1] as $block) {
            foreach ($this->parseTuples($block) as $tuple) {
                if (in_array((int) $tuple[2], [self::NETWORK_DEVICE_TYPE, self::ACCESS_POINT_TYPE], true)) {
                    $rows[] = [
                        'legacy_id'     => $this->nullIfBlank($tuple[0] ?? null),
                        'property_no'   => $this->nullIfBlank($tuple[1] ?? null),
                        'device_type'   => (int) ($tuple[2] ?? 0),
                        'description'   => $this->nullIfBlank($tuple[3] ?? null),
                        'location'      => $this->nullIfBlank($tuple[4] ?? null),
                        'date_entry'    => $this->nullIfBlank($tuple[5] ?? null),
                        'unit'          => $this->nullIfBlank($tuple[6] ?? null),
                        'owner'         => $this->nullIfBlank($tuple[7] ?? null),
                        'status'        => $this->nullIfBlank($tuple[8] ?? null),
                        'remarks'       => $this->nullIfBlank($tuple[9] ?? null),
                        'encodedby'     => $this->nullIfBlank($tuple[10] ?? null),
                        'amount'        => $this->nullIfBlank($tuple[11] ?? null),
                        'date_acquired' => $this->nullIfBlank($tuple[12] ?? null),
                        'serialno'      => $this->nullIfBlank($tuple[13] ?? null),
                        'category'      => $this->nullIfBlank($tuple[14] ?? null),
                    ];
                }
            }
        }

        return $rows;
    }

    private function prepareRows(array $rows): array
    {
        $rooms = Room::all(['id', 'name', 'code'])->mapWithKeys(function (Room $room) {
            return collect([$room->name, $room->code])
                ->filter()
                ->mapWithKeys(fn ($value) => [$this->key($value) => $room]);
        });

        $usersByEmail = User::all(['id', 'name', 'email'])
            ->filter(fn (User $user) => filled($user->email))
            ->keyBy(fn (User $user) => strtolower($user->email));

        $legacyOwners = $this->legacyOwnerEmails();
        $insertable = [];
        $duplicates = [];
        $missingOwners = [];
        $missingRooms = [];

        foreach ($rows as $row) {
            $serialNo = $this->nullIfBlank($row['serialno'] ?? null) ?: 'N/A';
            $propertyNo = $this->nullIfBlank($row['property_no'] ?? null);
            $description = $this->nullIfBlank($row['description'] ?? null) ?: 'Legacy network device';

            if ($this->isDuplicate($serialNo, $propertyNo, $description)) {
                $duplicates[] = $row;
                continue;
            }

            $ownerEmail = $legacyOwners[(string) ($row['owner'] ?? '')] ?? null;
            $owner = $ownerEmail ? ($usersByEmail[strtolower($ownerEmail)] ?? null) : null;
            if ($ownerEmail && ! $owner) {
                $missingOwners[$row['owner']] = $ownerEmail;
            }

            $room = $this->resolveRoom($row['location'] ?? null, $rooms);
            if (! $room && filled($row['location'] ?? null)) {
                $missingRooms[$row['location']] = true;
            }

            $remarks = collect([
                $this->nullIfBlank($row['remarks'] ?? null),
                $room ? null : 'Legacy location: ' . ($row['location'] ?? 'Unknown'),
                'Legacy ITJR equipment ID: ' . ($row['legacy_id'] ?? 'unknown'),
            ])->filter()->implode("\n");

            $insertable[] = [
                'source' => $row,
                'attributes' => [
                    'category'      => ((int) $row['device_type']) === self::ACCESS_POINT_TYPE ? 'Access Point' : 'Network Devices',
                    'owner_id'      => $owner?->id,
                    'property_no'   => $propertyNo,
                    'serial_no'     => $serialNo,
                    'description'   => $description,
                    'date_acquired' => $this->parseDate($row['date_acquired'] ?? null),
                    'amount'        => $this->parseAmount($row['amount'] ?? null),
                    'status'        => $this->mapStatus($row['status'] ?? null),
                    'room_id'       => $room?->id,
                    'remarks'       => $remarks ?: null,
                ],
            ];
        }

        return [
            'source_count'    => count($rows),
            'insertable'      => $insertable,
            'duplicates'      => $duplicates,
            'missing_owners'  => $missingOwners,
            'missing_rooms'   => array_keys($missingRooms),
        ];
    }

    private function report(array $prepared, bool $execute): void
    {
        $this->line($execute ? 'Mode: EXECUTE' : 'Mode: DRY RUN');
        $this->line('Source network rows: ' . $prepared['source_count']);
        $this->line('Insertable rows: ' . count($prepared['insertable']));
        $this->line('Skipped duplicates: ' . count($prepared['duplicates']));

        if (! empty($prepared['missing_owners'])) {
            $this->warn('Missing owner mappings in current users:');
            foreach ($prepared['missing_owners'] as $legacyId => $email) {
                $this->line("  Legacy owner {$legacyId}: {$email}");
            }
        }

        if (! empty($prepared['missing_rooms'])) {
            $this->warn('Unresolved rooms; legacy location will be preserved in remarks:');
            foreach ($prepared['missing_rooms'] as $location) {
                $this->line("  {$location}");
            }
        }

        $this->table(
            ['Legacy ID', 'Category', 'Description', 'Serial', 'Status', 'Owner ID', 'Room ID'],
            collect($prepared['insertable'])->take(15)->map(fn ($item) => [
                $item['source']['legacy_id'] ?? '',
                $item['attributes']['category'],
                $item['attributes']['description'],
                $item['attributes']['serial_no'],
                $item['attributes']['status'],
                $item['attributes']['owner_id'] ?? '',
                $item['attributes']['room_id'] ?? '',
            ])->all()
        );
    }

    private function legacyOwnerEmails(): array
    {
        return [
            '35' => 'mafrancisco@crc.pshs.edu.ph',
            '8'  => 'mmbarbon@crc.pshs.edu.ph',
            '50' => 'allido@crc.pshs.edu.ph',
            '71' => 'csromano@crc.pshs.edu.ph',
        ];
    }

    private function resolveRoom(?string $location, $rooms): ?Room
    {
        if (! filled($location)) {
            return null;
        }

        $aliases = [
            'server room' => 'server room',
            'guardhouse' => 'guard house',
            'boys dormitory' => 'dormitory building 1',
            'library building' => 'library',
            'finance and administrative division' => 'fad office',
        ];

        $key = $this->key($location);
        $key = $aliases[$key] ?? $key;

        return $rooms[$key] ?? null;
    }

    private function isDuplicate(string $serialNo, ?string $propertyNo, string $description): bool
    {
        $query = ICTEquipment::query();

        if ($serialNo !== '' && strtoupper($serialNo) !== 'N/A') {
            return $query->where('serial_no', $serialNo)->exists();
        }

        if (filled($propertyNo)) {
            return ICTEquipment::where('property_no', $propertyNo)
                ->where('description', $description)
                ->exists();
        }

        return false;
    }

    private function parseDate(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $clean = str_replace(',', '', $value);
        return is_numeric($clean) ? number_format((float) $clean, 2, '.', '') : null;
    }

    private function mapStatus(?string $status): string
    {
        return match ($status) {
            'For External Repair' => 'For Repair',
            default => $status ?: 'Good Working',
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

    private function key(?string $value): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim((string) $value)));
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
