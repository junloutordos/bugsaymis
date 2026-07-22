<?php

namespace App\Http\Middleware;

use App\Models\StudentAttendance\StudentAttendanceDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAttendanceDeviceMode
{
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        /** @var StudentAttendanceDevice|null $device */
        $device = $request->attributes->get('studentAttendanceDevice');

        if (! $device || $device->device_mode !== $mode) {
            abort(403, 'This attendance device is not authorized for the requested scanner mode.');
        }

        return $next($request);
    }
}
