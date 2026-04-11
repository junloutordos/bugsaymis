<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('StudentAttendance/Kiosk', [
            'gateLocations' => config('student_attendance.gate_locations', []),
        ]);
    }
}
