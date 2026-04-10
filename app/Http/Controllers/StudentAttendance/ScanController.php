<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Events\AttendanceScanEvent;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAttendance\StudentAttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScanController extends Controller
{
    /**
     * Process a barcode scan from the gate scanner.
     *
     * POST /student-attendance/scan
     * Body: { barcode: string, gate_location?: string, source?: string }
     *
     * Returns JSON — always. The kiosk Vue page reads this response to drive
     * the display (green flash for IN, amber for OUT, red for errors).
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode'       => ['required', 'string', 'max:50'],
            'gate_location' => ['nullable', 'string', 'max:100'],
            'source'        => ['nullable', 'string', 'in:gate_scanner,manual,api'],
        ]);

        $barcode      = trim($validated['barcode']);
        $gateLocation = $validated['gate_location'] ?? null;
        $source       = $validated['source'] ?? 'gate_scanner';

        // ── 1. Identify student ───────────────────────────────────────────────
        $student = DB::table('students')
            ->where('pisaysystemID', $barcode)
            ->first();

        if (! $student) {
            return response()->json([
                'status'  => 'not_found',
                'message' => "No student found for barcode: {$barcode}",
            ], 404);
        }

        // ── 2. Last scan for this student ─────────────────────────────────────
        $lastLog = StudentAttendanceLog::where('student_id', $student->id)
            ->orderByDesc('scan_time')
            ->first();

        // ── 3. Duplicate detection ────────────────────────────────────────────
        $duplicateWindowMinutes = (int) config('student_attendance.duplicate_window_minutes', 3);

        if ($lastLog) {
            $minutesSinceLast = $lastLog->scan_time->diffInMinutes(now());

            if ($minutesSinceLast < $duplicateWindowMinutes) {
                $payload = $this->buildPayload($student, $lastLog->type, $lastLog->scan_time, true);

                return response()->json([
                    'status'  => 'duplicate',
                    'message' => "Duplicate scan — last scan was {$minutesSinceLast}m ago.",
                    'data'    => $payload,
                ], 200);
            }
        }

        // ── 4. Determine IN / OUT ─────────────────────────────────────────────
        // Toggle: if last scan was IN → next is OUT, and vice versa.
        // First scan of the day (or ever) → IN.
        $type = ($lastLog && $lastLog->type === 'in') ? 'out' : 'in';

        // ── 5. Record the log (immutable — never update) ──────────────────────
        $log = StudentAttendanceLog::create([
            'student_id'    => $student->id,
            'raw_barcode'   => $barcode,
            'scan_time'     => now(),
            'type'          => $type,
            'source'        => $source,
            'gate_location' => $gateLocation,
            'recorded_by'   => Auth::id(),
        ]);

        // ── 6. Broadcast for real-time gate monitor ───────────────────────────
        $payload = $this->buildPayload($student, $type, $log->scan_time, false);
        event(new AttendanceScanEvent($payload));

        return response()->json([
            'status'  => 'ok',
            'message' => ($type === 'in' ? 'Time In' : 'Time Out') . ' recorded.',
            'data'    => $payload,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildPayload(object $student, string $type, $scanTime, bool $isDuplicate): array
    {
        return [
            'student_id'   => $student->id,
            'student_name' => trim("{$student->lastname}, {$student->firstname}"),
            'barcode'      => $student->pisaysystemID,
            'type'         => $type,
            'type_label'   => $type === 'in' ? 'Time In' : 'Time Out',
            'scan_time'    => $scanTime instanceof \Carbon\Carbon
                ? $scanTime->toIso8601String()
                : (string) $scanTime,
            'is_duplicate' => $isDuplicate,
        ];
    }
}
