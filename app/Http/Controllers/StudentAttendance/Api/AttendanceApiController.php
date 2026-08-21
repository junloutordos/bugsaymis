<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance\StudentAttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceApiController extends Controller
{
    /**
     * GET /api/mobile/attendance
     *
     * Returns paginated attendance logs for all students linked to
     * the authenticated parent contact. Optionally filter by student_id or date.
     *
     * Query params:
     *   student_id  — filter to a single student (must be linked to this parent)
     *   date        — YYYY-MM-DD, defaults to today
     *   per_page    — max 50, default 20
     */
    public function index(Request $request): JsonResponse
    {
        $parentContact = $request->user();

        // IDs of students linked to this parent
        $linkedStudentIds = DB::table('student_parent_contact')
            ->where('parent_contact_id', $parentContact->id)
            ->pluck('student_id');

        if ($linkedStudentIds->isEmpty()) {
            return response()->json([
                'data'       => [],
                'message'    => 'No students linked to your account.',
                'pagination' => null,
            ]);
        }

        $query = StudentAttendanceLog::whereIn('student_id', $linkedStudentIds)
            ->orderByDesc('scan_time');

        // Optional student filter (only if they actually own this student)
        if ($studentId = $request->input('student_id')) {
            if ($linkedStudentIds->contains((int) $studentId)) {
                $query->where('student_id', (int) $studentId);
            }
        }

        // Optional date filter — defaults to today
        $date = $request->input('date', now()->toDateString());
        $query->whereDate('scan_time', $date);

        $perPage = min((int) $request->input('per_page', 20), 50);
        $paginated = $query->paginate($perPage);

        // Resolve student names in one query
        $studentIds  = $paginated->pluck('student_id')->unique()->values();
        $studentMap  = DB::table('students')
            ->whereIn('id', $studentIds)
            ->get(['id', 'lastname', 'firstname'])
            ->keyBy('id');

        $data = $paginated->map(function ($log) use ($studentMap) {
            $s = $studentMap[$log->student_id] ?? null;
            return [
                'id'           => $log->id,
                'student_id'   => $log->student_id,
                'student_name' => $s ? trim("{$s->lastname}, {$s->firstname}") : 'Unknown',
                'type'         => $log->type,
                'type_label'   => $log->type === 'in' ? 'Time In' : 'Time Out',
                'scan_time'    => $log->scan_time->toIso8601String(),
                'gate_location'=> $log->gate_location,
                'source'       => $log->source,
            ];
        });

        return response()->json([
            'date'       => $date,
            'data'       => $data,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'has_more'     => $paginated->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/mobile/attendance/{studentId}/today
     *
     * Quick summary for a single student: their last IN/OUT status for today.
     * Used by the Flutter home screen widget.
     */
    public function today(Request $request, int $studentId): JsonResponse
    {
        $parentContact = $request->user();

        // Verify this parent is linked to the requested student
        $linked = DB::table('student_parent_contact')
            ->where('parent_contact_id', $parentContact->id)
            ->where('student_id', $studentId)
            ->exists();

        if (! $linked) {
            return response()->json(['message' => 'Student not linked to your account.'], 403);
        }

        $todayLogs = StudentAttendanceLog::where('student_id', $studentId)
            ->whereDate('scan_time', today())
            ->orderBy('scan_time')
            ->get(['id', 'type', 'scan_time', 'gate_location']);

        $lastLog = $todayLogs->last();

        $student = DB::table('students')->where('id', $studentId)->first(['id', 'lastname', 'firstname']);

        return response()->json([
            'student'      => $student ? [
                'id'   => $student->id,
                'name' => trim("{$student->lastname}, {$student->firstname}"),
            ] : null,
            'date'         => today()->toDateString(),
            'last_status'  => $lastLog?->type,                           // 'in' | 'out' | null
            'last_scan'    => $lastLog?->scan_time->toIso8601String(),
            'total_scans'  => $todayLogs->count(),
            'logs'         => $todayLogs->map(fn ($l) => [
                'type'          => $l->type,
                'type_label'    => $l->type === 'in' ? 'Time In' : 'Time Out',
                'scan_time'     => $l->scan_time->toIso8601String(),
                'gate_location' => $l->gate_location,
            ]),
        ]);
    }
}
