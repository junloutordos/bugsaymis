<?php

namespace App\Http\Controllers\NetworkInventory;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\NetworkScanLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NetworkInventoryController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'MIS']);
    }

    // ── Inertia: Index page ───────────────────────────────────────────────────

    public function index()
    {
        $lastSnmp  = NetworkScanLog::where('source', 'snmp')->latest('scanned_at')->first();
        $lastCloud = NetworkScanLog::where('source', 'ruijie_cloud')->latest('scanned_at')->first();

        $summary = [
            'total'    => NetworkDevice::count(),
            'online'   => NetworkDevice::where('is_online', true)->count(),
            'wired'    => NetworkDevice::where('connection_type', 'wired')->count(),
            'wireless' => NetworkDevice::where('connection_type', 'wireless')->count(),
            'new_today'=> NetworkDevice::whereDate('first_seen_at', today())->count(),
        ];

        $vlans = NetworkDevice::whereNotNull('vlan_id')
            ->selectRaw('vlan_id, vlan_name, count(*) as count')
            ->groupBy('vlan_id', 'vlan_name')
            ->orderBy('vlan_id')
            ->get();

        $aps = NetworkDevice::whereNotNull('ap_name')
            ->where('connection_type', 'wireless')
            ->selectRaw('ap_name, count(*) as count')
            ->groupBy('ap_name')
            ->orderByDesc('count')
            ->get();

        return Inertia::render('NetworkInventory/Index', [
            'summary'   => $summary,
            'vlans'     => $vlans,
            'aps'       => $aps,
            'isAdmin'   => $this->isAdmin(),
            'lastSnmpScan'  => $lastSnmp ? [
                'scanned_at'     => $lastSnmp->scanned_at->toIso8601String(),
                'devices_online' => $lastSnmp->devices_online,
                'status'         => $lastSnmp->status,
            ] : null,
            'lastCloudSync' => $lastCloud ? [
                'scanned_at'     => $lastCloud->scanned_at->toIso8601String(),
                'devices_online' => $lastCloud->devices_online,
                'status'         => $lastCloud->status,
            ] : null,
        ]);
    }

    // ── API: Device list (paginated, filterable) ──────────────────────────────

    public function devices(Request $request): JsonResponse
    {
        $query = NetworkDevice::query()->orderBy('ip_address');

        if ($request->filled('search')) {
            $q = $request->query('search');
            $query->where(fn ($w) =>
                $w->where('ip_address',  'like', "%{$q}%")
                  ->orWhere('mac_address', 'like', "%{$q}%")
                  ->orWhere('hostname',    'like', "%{$q}%")
                  ->orWhere('label',       'like', "%{$q}%")
                  ->orWhere('vendor',      'like', "%{$q}%")
            );
        }

        if ($request->filled('vlan_id'))         $query->where('vlan_id', $request->query('vlan_id'));
        if ($request->filled('connection_type'))  $query->where('connection_type', $request->query('connection_type'));
        if ($request->filled('device_type'))      $query->where('device_type', $request->query('device_type'));
        if ($request->filled('ap_name'))          $query->where('ap_name', $request->query('ap_name'));
        if ($request->filled('data_source'))      $query->where('data_source', $request->query('data_source'));

        if ($request->filled('status')) {
            $request->query('status') === 'online'
                ? $query->where('is_online', true)
                : $query->where('is_online', false);
        }

        return response()->json($query->paginate(50));
    }

    // ── Inertia: Device detail page ───────────────────────────────────────────

    public function show(NetworkDevice $networkDevice)
    {
        return Inertia::render('NetworkInventory/Show', [
            'device'  => $networkDevice,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    // ── API: Update device label / type / notes ───────────────────────────────

    public function update(Request $request, NetworkDevice $networkDevice): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);

        $validated = $request->validate([
            'label'       => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:computer,phone,tablet,printer,ap,switch,camera,iot,unknown'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        $networkDevice->update($validated);

        return response()->json([
            'message' => 'Device updated.',
            'device'  => $networkDevice->fresh(),
        ]);
    }

    // ── API: Scan logs ────────────────────────────────────────────────────────

    public function scanLogs(Request $request): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);

        $logs = NetworkScanLog::orderByDesc('scanned_at')->paginate(30);
        return response()->json($logs);
    }
}
