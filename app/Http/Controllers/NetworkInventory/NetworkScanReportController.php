<?php

namespace App\Http\Controllers\NetworkInventory;

use App\Http\Controllers\Controller;
use App\Services\NetworkInventory\NetworkDeviceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkScanReportController extends Controller
{
    public function __construct(private readonly NetworkDeviceSyncService $sync) {}

    /**
     * POST /api/network/scan-report
     * Receives scan data from the Ubuntu SNMP agent.
     *
     * Expected payload:
     * {
     *   "agent_version": "1.0",
     *   "devices": [
     *     {
     *       "mac_address": "aa:bb:cc:dd:ee:ff",
     *       "ip_address": "192.168.1.10",
     *       "hostname": "PC-ADMIN",
     *       "vendor": "Dell Inc.",
     *       "connection_type": "wired",
     *       "vlan_id": 10,
     *       "vlan_name": "Admin",
     *       "ap_name": null,
     *       "ssid": null,
     *       "signal_dbm": null
     *     },
     *     ...
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_version'              => ['nullable', 'string', 'max:20'],
            'devices'                    => ['required', 'array'],
            'devices.*.mac_address'      => ['required', 'string'],
            'devices.*.ip_address'       => ['nullable', 'string'],
            'devices.*.hostname'         => ['nullable', 'string', 'max:255'],
            'devices.*.vendor'           => ['nullable', 'string', 'max:255'],
            'devices.*.connection_type'  => ['nullable', 'string', 'in:wired,wireless,unknown'],
            'devices.*.vlan_id'          => ['nullable', 'integer'],
            'devices.*.vlan_name'        => ['nullable', 'string', 'max:100'],
            'devices.*.ap_name'          => ['nullable', 'string', 'max:255'],
            'devices.*.ssid'             => ['nullable', 'string', 'max:255'],
            'devices.*.signal_dbm'       => ['nullable', 'integer'],
        ]);

        $log = $this->sync->syncFromSnmp(
            devices:      $validated['devices'],
            agentVersion: $validated['agent_version'] ?? '1.0',
        );

        return response()->json([
            'message'        => 'Scan report received.',
            'devices_synced' => $log->devices_found,
            'devices_online' => $log->devices_online,
            'duration_ms'    => $log->duration_ms,
        ], 200);
    }
}
