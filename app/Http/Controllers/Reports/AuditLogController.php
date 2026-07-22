<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Administrator');
    }
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->boolean('guard_activity')) {
            $query->where(fn ($guardQuery) => $guardQuery
                ->where('action', 'like', 'guard.%')
                ->orWhere('action', 'like', 'admin.guard_%'));
        }

        if ($request->filled('guard_id')) {
            $query->where('user_id', $request->integer('guard_id'));
        }

        if ($request->filled('kiosk_device_id')) {
            $query->where('new_values->kiosk_device_id', $request->integer('kiosk_device_id'));
        }

        // Simple search across user name, action, auditable_type, auditable_id and ip
        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'like', "%{$q}%")
                    ->orWhere('auditable_type', 'like', "%{$q}%")
                    ->orWhere('auditable_id', 'like', "%{$q}%")
                    ->orWhere('ip_address', 'like', "%{$q}%")
                    ->orWhere('url', 'like', "%{$q}%")
                    ->orWhereJsonContains('old_values', $q)
                    ->orWhereJsonContains('new_values', $q);

                // match user name
                $sub->orWhereHas('user', function ($u) use ($q) {
                    $u->where('name', 'like', "%{$q}%");
                });
            });
        }

        $auditLogs = $query->paginate(10)->withQueryString();

        return Inertia::render('Reports/AuditLogs/Index', [
            'auditLogs' => $auditLogs,
            'guards' => User::whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'Security Guard'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'kioskDevices' => StudentAttendanceDevice::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'guard_activity', 'guard_id', 'kiosk_device_id']),
        ]);
    }
}
