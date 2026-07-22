<?php

namespace App\Http\Middleware;

use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Services\StudentAttendance\KioskAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAttendancePhotoAccess
{
    public function __construct(private KioskAccessService $accessService) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var StudentAttendanceDevice|null $device */
        $device = $request->attributes->get('studentAttendanceDevice');

        if ($device?->isStudentSelfScan()) {
            return $next($request);
        }

        $operator = $device?->isGuardCamera()
            ? $this->accessService->currentOperator($request, $device, true)
            : null;

        if (! $operator) {
            abort(403, 'This attendance device is not authorized to view student photos.');
        }

        $request->attributes->set('studentAttendanceOperator', $operator);

        return $next($request);
    }
}
