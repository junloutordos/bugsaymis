<?php

namespace App\Services;

use App\Models\AtlasSentinelContainmentSetting;
use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentContainmentIncident;
use App\Models\IctEquipmentDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AtlasSentinelContainmentService
{
    public function recordIncident(IctEquipmentDevice $device, string $reason, array $detail): IctEquipmentContainmentIncident
    {
        return DB::transaction(function () use ($device, $reason, $detail) {
            $incident = IctEquipmentContainmentIncident::create([
                'device_id' => $device->id,
                'reason' => $reason,
                'detail' => $detail,
                'triggered_at' => now(),
            ]);

            $device->update([
                'containment_status' => 'contained',
                'containment_incident_id' => $incident->id,
            ]);

            IctEquipmentAlert::create([
                'device_id' => $device->id,
                'equipment_id' => $device->equipment_id,
                'code' => $reason === 'av_signal' ? 'malware_detected' : ($reason === 'network_anomaly' ? 'network_anomaly' : 'device_contained'),
                'issue' => 'Device automatically contained: '.$reason,
                'severity' => 'critical',
                'status' => 'open',
                'probable_cause' => json_encode($detail),
            ]);

            return $incident;
        });
    }

    public function confirmIncident(IctEquipmentContainmentIncident $incident, User $confirmedBy): void
    {
        $incident->update([
            'confirmed_by' => $confirmedBy->id,
            'confirmed_at' => now(),
        ]);
    }

    public function releaseIncident(IctEquipmentContainmentIncident $incident, ?User $releasedBy): void
    {
        DB::transaction(function () use ($incident, $releasedBy) {
            $incident->update([
                'released_at' => now(),
                'released_by' => $releasedBy?->id,
            ]);

            $incident->device()->update([
                'containment_status' => 'none',
                'containment_incident_id' => null,
            ]);

            IctEquipmentAlert::create([
                'device_id' => $incident->device_id,
                'equipment_id' => $incident->device?->equipment_id,
                'code' => 'device_released',
                'issue' => 'Device released from containment',
                'severity' => 'info',
                'status' => 'resolved',
            ]);
        });
    }

    public function autoReleaseExpired(): int
    {
        $minutes = AtlasSentinelContainmentSetting::current()->auto_release_minutes;
        $cutoff = now()->subMinutes($minutes);

        $expired = IctEquipmentContainmentIncident::whereNull('released_at')
            ->whereNull('confirmed_at')
            ->where('triggered_at', '<=', $cutoff)
            ->get();

        foreach ($expired as $incident) {
            $this->releaseIncident($incident, null);
        }

        return $expired->count();
    }

    public function toggleExempt(IctEquipmentDevice $device, bool $exempt): void
    {
        $device->update(['containment_exempt' => $exempt]);
    }
}
