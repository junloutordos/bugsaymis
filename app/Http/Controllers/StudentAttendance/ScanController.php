<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Events\AttendanceScanEvent;
use App\Http\Controllers\Controller;
use App\Jobs\StudentAttendance\SendAttendanceSmsJob;
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
        // Rules (all scoped to TODAY only — yesterday's unmatched Time In is ignored):
        //   • No Time In recorded today yet  → Time In  (first scan of the day,
        //                                      regardless of clock time; also resets
        //                                      a student who forgot to Time Out the
        //                                      previous school day)
        //   • Already has a Time In today    → Time Out (subsequent scan)
        //   • The 12:00 PM cutoff applies to the Time Out direction:
        //     scans before noon default to Time In unless already checked in;
        //     scans from noon onward are always Time Out (student is leaving).
        $now   = now();
        $today = $now->toDateString();

        $alreadyInToday = StudentAttendanceLog::where('student_id', $student->id)
            ->where('type', 'in')
            ->whereDate('scan_time', $today)
            ->exists();

        if (! $alreadyInToday) {
            // First scan of this calendar day → always Time In, even if after noon
            // (handles late arrivals and the "forgot to Time Out yesterday" reset)
            $type = 'in';
        } else {
            // Student already checked in today; after noon it becomes Time Out.
            // A second scan before noon is also treated as Time Out (early exit).
            $type = 'out';
        }

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

        // ── 7. Queue SMS notification to linked parents ───────────────────────
        SendAttendanceSmsJob::dispatch(
            $student->id,
            $type,
            $log->scan_time->toIso8601String(),
            $gateLocation,
        );

        return response()->json([
            'status'  => 'ok',
            'message' => ($type === 'in' ? 'Time In' : 'Time Out') . ' recorded.',
            'data'    => $payload,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildPayload(object $student, string $type, $scanTime, bool $isDuplicate): array
    {
        $photoUrl = $student->img
            ? asset('storage/students_profile_picture/' . $student->img)
            : null;

        return [
            'student_id'   => $student->id,
            'student_name' => trim("{$student->lastname}, {$student->firstname}"),
            'first_name'   => $student->firstname,
            'barcode'      => $student->pisaysystemID,
            'year_level'   => $student->batch ?? null,
            'photo_url'    => $photoUrl,
            'type'         => $type,
            'type_label'   => $type === 'in' ? 'Time In' : 'Time Out',
            'scan_time'    => $scanTime instanceof \Carbon\Carbon
                ? $scanTime->toIso8601String()
                : (string) $scanTime,
            'gate_location' => $student->gate_location ?? null,
            'is_duplicate' => $isDuplicate,
        ];
    }
}
