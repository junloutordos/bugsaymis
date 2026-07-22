<?php

namespace App\Services\StudentAttendance;

use App\Events\AttendanceScanEvent;
use App\Jobs\StudentAttendance\SendAttendanceSmsJob;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttendanceScanService
{
    public function record(
        string $barcode,
        string $scanUuid,
        ?Carbon $deviceScanTime,
        StudentAttendanceDevice $device,
        User $operator,
        string $captureMethod = 'camera',
    ): array {
        $student = DB::table('students')->where('pisaysystemID', $barcode)->first();

        if (! $student) {
            return [
                'http_status' => 404,
                'status' => 'not_found',
                'message' => "No student found for barcode: {$barcode}",
                'data' => null,
            ];
        }

        return Cache::lock("student-attendance:scan:{$student->id}", 5)->block(3, function () use (
            $student,
            $barcode,
            $scanUuid,
            $deviceScanTime,
            $device,
            $operator,
            $captureMethod,
        ) {
            $existing = StudentAttendanceLog::where('scan_uuid', $scanUuid)
                ->where('kiosk_device_id', $device->id)
                ->first();

            if ($existing) {
                return [
                    'http_status' => 200,
                    'status' => 'duplicate',
                    'message' => 'This scan was already recorded.',
                    'data' => $this->buildPayload($student, $existing, true),
                ];
            }

            $lastLog = StudentAttendanceLog::where('student_id', $student->id)
                ->orderByDesc('scan_time')
                ->first();

            $duplicateWindowSeconds = (int) config('student_attendance.duplicate_window_minutes', 3) * 60;

            if ($lastLog && $lastLog->scan_time->diffInSeconds(now()) < $duplicateWindowSeconds) {
                return [
                    'http_status' => 200,
                    'status' => 'duplicate',
                    'message' => 'Duplicate scan — the previous attendance record was kept.',
                    'data' => $this->buildPayload($student, $lastLog, true),
                ];
            }

            $lastToday = StudentAttendanceLog::where('student_id', $student->id)
                ->whereDate('scan_time', now()->toDateString())
                ->orderByDesc('scan_time')
                ->first();

            $type = $lastToday?->type === 'in' ? 'out' : 'in';

            $log = StudentAttendanceLog::create([
                'student_id' => $student->id,
                'raw_barcode' => $barcode,
                'scan_time' => now(),
                'type' => $type,
                'source' => $captureMethod === 'manual' ? 'manual' : 'gate_scanner',
                'gate_location' => $device->gate_location,
                'recorded_by' => $operator->id,
                'kiosk_device_id' => $device->id,
                'scan_uuid' => $scanUuid,
                'device_scan_time' => $deviceScanTime,
                'capture_method' => $captureMethod,
            ]);

            $device->forceFill(['last_seen_at' => now()])->save();

            $payload = $this->buildPayload($student, $log, false);

            rescue(fn () => event(new AttendanceScanEvent($payload)));

            SendAttendanceSmsJob::dispatch(
                $student->id,
                $type,
                $log->scan_time->toIso8601String(),
                config("student_attendance.gate_locations.{$device->gate_location}", $device->gate_location),
            );

            return [
                'http_status' => 200,
                'status' => 'ok',
                'message' => ($type === 'in' ? 'Time In' : 'Time Out').' recorded.',
                'data' => $payload,
            ];
        });
    }

    private function buildPayload(object $student, StudentAttendanceLog $log, bool $isDuplicate): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => trim("{$student->lastname}, {$student->firstname}"),
            'first_name' => $student->firstname,
            'barcode' => $student->pisaysystemID,
            'year_level' => $student->batch ?? null,
            'photo_url' => ! empty($student->img)
                ? route('student-attendance.student.photo', $student->id)
                : null,
            'type' => $log->type,
            'type_label' => $log->type === 'in' ? 'Time In' : 'Time Out',
            'scan_time' => $log->scan_time->toIso8601String(),
            'gate_location' => $log->gate_location,
            'is_duplicate' => $isDuplicate,
        ];
    }
}
