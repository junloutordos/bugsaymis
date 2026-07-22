<?php

namespace App\Http\Middleware;

use App\Models\StudentAttendance\StudentAttendanceDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAttendanceDevice
{
    public const COOKIE = 'student_attendance_device';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::COOKIE);
        $device = StudentAttendanceDevice::resolveToken($token);

        if (! $device) {
            abort(403, 'This device is not registered as an active attendance kiosk.');
        }

        $request->attributes->set('studentAttendanceDevice', $device);

        return $next($request);
    }
}
