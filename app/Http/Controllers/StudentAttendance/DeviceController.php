<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureStudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceKioskAccessLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('StudentAttendance/Devices', [
            'devices' => StudentAttendanceDevice::with('pairedBy:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (StudentAttendanceDevice $device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'gate_location' => $device->gate_location,
                    'gate_label' => config("student_attendance.gate_locations.{$device->gate_location}", $device->gate_location),
                    'device_mode' => $device->device_mode,
                    'device_mode_label' => $device->isStudentSelfScan() ? 'Student Self-Scan' : 'Guard Camera',
                    'is_active' => $device->is_active,
                    'paired_by' => $device->pairedBy?->name,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'created_at' => $device->created_at?->toIso8601String(),
                ]),
            'guards' => User::whereHas('roles', fn ($query) => $query->where('name', 'Security Guard'))
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '<>', 'inactive'))
                ->with(['attendanceKioskCredential.setBy:id,name'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $guard) => [
                    'id' => $guard->id,
                    'name' => $guard->name,
                    'has_pin' => (bool) $guard->attendanceKioskCredential,
                    'is_active' => $guard->attendanceKioskCredential?->is_active ?? false,
                    'pin_changed_at' => $guard->attendanceKioskCredential?->pin_changed_at?->toIso8601String(),
                    'set_by' => $guard->attendanceKioskCredential?->setBy?->name,
                ]),
            'accessLogs' => StudentAttendanceKioskAccessLog::with([
                    'kioskDevice:id,name',
                    'user:id,name',
                ])
                ->orderByDesc('occurred_at')
                ->limit(100)
                ->get()
                ->map(fn (StudentAttendanceKioskAccessLog $log) => [
                    'id' => $log->id,
                    'device' => $log->kioskDevice?->name,
                    'operator' => $log->user?->name,
                    'event' => $log->event,
                    'reason' => $log->reason,
                    'occurred_at' => $log->occurred_at?->toIso8601String(),
                ]),
        ]);
    }

    public function pair(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'gate_location' => ['required', Rule::in(array_keys(config('student_attendance.gate_locations', [])))],
            'device_mode' => ['sometimes', Rule::in([
                StudentAttendanceDevice::MODE_GUARD_CAMERA,
                StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN,
            ])],
        ]);

        $token = Str::random(80);

        $device = StudentAttendanceDevice::create([
            ...$validated,
            'device_mode' => $validated['device_mode'] ?? StudentAttendanceDevice::MODE_GUARD_CAMERA,
            'token_hash' => hash('sha256', $token),
            'paired_by' => $request->user()->id,
        ]);

        AuditLogger::log([
            'action' => $device->isStudentSelfScan()
                ? 'admin.self_scan_device.paired'
                : 'admin.guard_device.paired',
            'auditable_type' => StudentAttendanceDevice::class,
            'auditable_id' => $device->id,
            'new_values' => [
                'name' => $device->name,
                'gate_location' => $device->gate_location,
                'device_mode' => $device->device_mode,
            ],
        ]);

        return back()
            ->with('success', 'This device is now registered for gate attendance.')
            ->withCookie(cookie(
                EnsureStudentAttendanceDevice::COOKIE,
                $token,
                60 * 24 * 365,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'strict',
            ));
    }

    public function revoke(Request $request, StudentAttendanceDevice $device): RedirectResponse
    {
        $device->update(['is_active' => false]);

        AuditLogger::log([
            'action' => $device->isStudentSelfScan()
                ? 'admin.self_scan_device.revoked'
                : 'admin.guard_device.revoked',
            'auditable_type' => StudentAttendanceDevice::class,
            'auditable_id' => $device->id,
            'new_values' => [
                'name' => $device->name,
                'gate_location' => $device->gate_location,
                'device_mode' => $device->device_mode,
                'is_active' => false,
            ],
        ]);

        $response = back()->with('success', 'Kiosk access revoked.');
        $token = $request->cookie(EnsureStudentAttendanceDevice::COOKIE);

        if ($token && hash_equals($device->token_hash, hash('sha256', $token))) {
            $response->withoutCookie(EnsureStudentAttendanceDevice::COOKIE);
        }

        return $response;
    }
}
