<?php

namespace App\Console\Commands;

use App\Models\AtlasSentinelRelease;
use App\Models\IctEquipmentDevice;
use App\Services\AtlasSentinelFleetStateService;
use Illuminate\Console\Command;

class AtlasSentinelAudit extends Command
{
    protected $signature = 'atlas-sentinel:audit {--all : Show current devices too}';
    protected $description = 'Print Atlas Sentinel fleet versions, check-in age, and update state';

    public function handle(AtlasSentinelFleetStateService $fleetStateService): int
    {
        $latestRelease = AtlasSentinelRelease::latestRelease();

        $this->line('App time: ' . now()->format('Y-m-d H:i:s'));
        $this->line('Latest release: ' . ($latestRelease?->version ?? 'none'));

        $devices = IctEquipmentDevice::with(['equipment.owner', 'equipment.room'])
            ->orderBy('hostname')
            ->get();

        $this->line('Device count: ' . $devices->count());

        $devices
            ->groupBy(fn (IctEquipmentDevice $device) => $device->agent_version ?: 'unknown')
            ->sortKeys()
            ->each(fn ($group, string $version) => $this->line("Version {$version}: {$group->count()}"));

        $rows = $devices
            ->map(function (IctEquipmentDevice $device) use ($fleetStateService, $latestRelease) {
                $minutesSince = $fleetStateService->minutesSinceCheckin($device->last_checkin_at);
                $state = $fleetStateService->classify(
                    $device->last_checkin_at,
                    $device->agent_version,
                    $latestRelease?->version,
                );

                return [
                    'state' => $state,
                    'hostname' => $device->hostname ?? "Device #{$device->id}",
                    'version' => $device->agent_version ?? 'unknown',
                    'age_min' => $minutesSince ?? 'never',
                    'update' => $device->last_update_result ?? '-',
                    'last_update' => $device->last_update_attempted_at?->format('Y-m-d H:i') ?? '-',
                    'owner' => $device->equipment?->owner?->name ?? '-',
                    'room' => $device->equipment?->room?->name ?? '-',
                ];
            })
            ->filter(fn (array $row) => $this->option('all') || $row['state'] !== 'current')
            ->values();

        if ($rows->isEmpty()) {
            $this->info('No non-current devices found.');
            return self::SUCCESS;
        }

        $this->table(
            ['State', 'Host', 'Version', 'Age Min', 'Update', 'Last Update', 'Owner', 'Room'],
            $rows->map(fn (array $row) => [
                $row['state'],
                $row['hostname'],
                $row['version'],
                $row['age_min'],
                $row['update'],
                $row['last_update'],
                $row['owner'],
                $row['room'],
            ])->all()
        );

        return self::SUCCESS;
    }
}
