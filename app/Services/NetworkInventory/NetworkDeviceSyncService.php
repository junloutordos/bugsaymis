<?php

namespace App\Services\NetworkInventory;

use App\Models\NetworkDevice;
use App\Models\NetworkScanLog;
use Carbon\Carbon;
use Illuminate\Support\Str;

class NetworkDeviceSyncService
{
    /**
     * Process a batch of devices from the SNMP agent.
     * Upserts by MAC address, merges with existing cloud data.
     */
    public function syncFromSnmp(array $devices, string $agentVersion = '1.0'): NetworkScanLog
    {
        $start  = microtime(true);
        $now    = Carbon::now();
        $online = 0;

        foreach ($devices as $d) {
            $mac = $this->normalizeMac($d['mac_address'] ?? '');
            if (! $mac) continue;

            $existing = NetworkDevice::where('mac_address', $mac)->first();

            $data = [
                'mac_address'     => $mac,
                'ip_address'      => $d['ip_address']      ?? null,
                'hostname'        => $d['hostname']         ?? null,
                'vendor'          => $d['vendor']           ?? null,
                'connection_type' => $d['connection_type']  ?? 'unknown',
                'vlan_id'         => $d['vlan_id']          ?? null,
                'vlan_name'       => $d['vlan_name']        ?? null,
                'ap_name'         => $d['ap_name']          ?? null,
                'ssid'            => $d['ssid']             ?? null,
                'signal_dbm'      => $d['signal_dbm']       ?? null,
                'is_online'       => true,
                'last_seen_at'    => $now,
                'last_source'     => 'snmp',
            ];

            if ($existing) {
                // Preserve admin fields and cloud data
                $data['first_seen_at'] = $existing->first_seen_at ?? $now;
                $data['data_source']   = $existing->data_source === 'ruijie_cloud' ? 'both' : 'snmp';
                // Don't overwrite cloud-only fields if SNMP doesn't have them
                if (empty($data['ap_name']) && $existing->ap_name)   $data['ap_name']   = $existing->ap_name;
                if (empty($data['ssid'])    && $existing->ssid)      $data['ssid']      = $existing->ssid;
                if (empty($data['signal_dbm']) && $existing->signal_dbm) $data['signal_dbm'] = $existing->signal_dbm;
                $existing->update($data);
            } else {
                $data['first_seen_at'] = $now;
                $data['data_source']   = 'snmp';
                NetworkDevice::create($data);
            }

            $online++;
        }

        // Mark devices not seen in this scan as offline (SNMP-sourced only)
        $seenMacs = collect($devices)->map(fn ($d) => $this->normalizeMac($d['mac_address'] ?? ''))->filter()->values();
        NetworkDevice::where('last_source', 'snmp')
            ->whereNotIn('mac_address', $seenMacs)
            ->update(['is_online' => false]);

        $duration = (int) round((microtime(true) - $start) * 1000);

        return NetworkScanLog::create([
            'source'        => 'snmp',
            'scanned_at'    => $now,
            'duration_ms'   => $duration,
            'devices_found' => count($devices),
            'devices_online'=> $online,
            'status'        => 'success',
            'agent_version' => $agentVersion,
        ]);
    }

    /**
     * Process a batch of devices from Ruijie Cloud API.
     * Upserts by MAC address, merges with existing SNMP data.
     */
    public function syncFromRuijieCloud(array $devices): NetworkScanLog
    {
        $start  = microtime(true);
        $now    = Carbon::now();
        $online = 0;

        foreach ($devices as $d) {
            $mac = $this->normalizeMac($d['mac_address'] ?? '');
            if (! $mac) continue;

            $existing = NetworkDevice::where('mac_address', $mac)->first();

            $data = [
                'mac_address'     => $mac,
                'ip_address'      => $d['ip_address']     ?? null,
                'hostname'        => $d['hostname']        ?? null,
                'vendor'          => $d['vendor']          ?? null,
                'connection_type' => 'wireless',
                'ap_name'         => $d['ap_name']         ?? null,
                'ssid'            => $d['ssid']            ?? null,
                'signal_dbm'      => $d['signal_dbm']      ?? null,
                'vlan_id'         => $d['vlan_id']         ?? null,
                'vlan_name'       => $d['vlan_name']       ?? null,
                'is_online'       => true,
                'last_seen_at'    => $now,
                'last_source'     => 'ruijie_cloud',
                'ruijie_cloud_id' => $d['cloud_id']        ?? null,
            ];

            if ($existing) {
                $data['first_seen_at'] = $existing->first_seen_at ?? $now;
                $data['data_source']   = $existing->data_source === 'snmp' ? 'both' : 'ruijie_cloud';
                // Don't overwrite SNMP VLAN data with empty cloud data
                if (empty($data['vlan_id'])   && $existing->vlan_id)   $data['vlan_id']   = $existing->vlan_id;
                if (empty($data['vlan_name']) && $existing->vlan_name) $data['vlan_name'] = $existing->vlan_name;
                $existing->update($data);
            } else {
                $data['first_seen_at'] = $now;
                $data['data_source']   = 'ruijie_cloud';
                NetworkDevice::create($data);
            }

            $online++;
        }

        $duration = (int) round((microtime(true) - $start) * 1000);

        return NetworkScanLog::create([
            'source'         => 'ruijie_cloud',
            'scanned_at'     => $now,
            'duration_ms'    => $duration,
            'devices_found'  => count($devices),
            'devices_online' => $online,
            'status'         => 'success',
        ]);
    }

    /**
     * Normalize MAC to lowercase colon-separated format: aa:bb:cc:dd:ee:ff
     */
    public function normalizeMac(string $mac): ?string
    {
        $cleaned = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $mac));
        if (strlen($cleaned) !== 12) return null;
        return implode(':', str_split($cleaned, 2));
    }
}
