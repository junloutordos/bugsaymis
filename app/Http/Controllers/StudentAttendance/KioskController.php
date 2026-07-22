<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureStudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceKioskOperator;
use App\Services\StudentAttendance\KioskAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    public function __construct(private KioskAccessService $accessService) {}

    public function index(Request $request): Response
    {
        $device = StudentAttendanceDevice::resolveToken(
            $request->cookie(EnsureStudentAttendanceDevice::COOKIE)
        );

        $operator = $device?->isGuardCamera()
            ? $this->accessService->currentOperator($request, $device)
            : null;

        return Inertia::render('StudentAttendance/Kiosk', [
            'gateLocations' => config('student_attendance.gate_locations', []),
            'device' => $device ? [
                'id' => $device->id,
                'name' => $device->name,
                'gate_location' => $device->gate_location,
                'gate_label' => config("student_attendance.gate_locations.{$device->gate_location}", $device->gate_location),
                'device_mode' => $device->device_mode,
            ] : null,
            'canPairDevice' => $request->user()?->isSuperAdmin() ?? false,
            'operator' => $operator ? [
                'id' => $operator->id,
                'name' => $operator->name,
                'is_administrator' => $operator->isSuperAdmin(),
            ] : null,
            'guards' => $device?->isGuardCamera() && ! $operator
                ? StudentAttendanceKioskOperator::where('is_active', true)
                    ->whereHas('user', fn ($query) => $query
                        ->where(fn ($statusQuery) => $statusQuery
                            ->whereNull('status')
                            ->orWhere('status', '<>', 'inactive'))
                        ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'Security Guard')))
                    ->with('user:id,name')
                    ->get()
                    ->map(fn (StudentAttendanceKioskOperator $credential) => [
                        'id' => $credential->user->id,
                        'name' => $credential->user->name,
                    ])
                    ->sortBy('name')
                    ->values()
                : [],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var StudentAttendanceDevice $device */
        $device = $request->attributes->get('studentAttendanceDevice');

        $device->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'status' => 'ok',
            'operator' => [
                'id' => $request->attributes->get('studentAttendanceOperator')->id,
                'name' => $request->attributes->get('studentAttendanceOperator')->name,
                'is_administrator' => $request->attributes->get('studentAttendanceOperator')->isSuperAdmin(),
            ],
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'gate_location' => $device->gate_location,
            ],
        ]);
    }
}
