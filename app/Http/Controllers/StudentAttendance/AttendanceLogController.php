<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance\StudentAttendanceLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('students.attendance.view');

        $query = StudentAttendanceLog::with([
            'student',
            'recordedBy:id,name',
            'kioskDevice:id,name',
        ])
            ->orderByDesc('scan_time');

        if ($date = $request->input('date')) {
            $query->whereDate('scan_time', $date);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('StudentAttendance/Logs', [
            'logs'    => $logs,
            'filters' => $request->only(['date', 'type', 'student_id']),
        ]);
    }
}
