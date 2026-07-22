<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Services\StudentAttendance\AttendanceScanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockTimeoutException;

class ScanController extends Controller
{
    public function __construct(private AttendanceScanService $scanService) {}

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
            'scan_uuid' => ['required', 'uuid'],
            'device_scan_time' => ['nullable', 'date'],
            'capture_method' => ['nullable', 'string', 'in:camera,manual'],
        ]);

        /** @var StudentAttendanceDevice $device */
        $device = $request->attributes->get('studentAttendanceDevice');

        try {
            $result = $this->scanService->record(
                trim($validated['barcode']),
                $validated['scan_uuid'],
                isset($validated['device_scan_time']) ? Carbon::parse($validated['device_scan_time']) : null,
                $device,
                $request->attributes->get('studentAttendanceOperator'),
                $validated['capture_method'] ?? 'camera',
            );
        } catch (LockTimeoutException) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Another scan for this student is still processing. Please scan again.',
                'data' => null,
            ], 409);
        }

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['http_status']);
    }
}
