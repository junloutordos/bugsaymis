<?php

namespace App\Listeners\StudentAttendance;

use App\Events\AttendanceScanEvent;
use App\Mail\StudentAttendanceMail;
use App\Services\StudentAttendance\FcmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyParentsOnScan
{
    public function __construct(private FcmService $fcm) {}

    public function handle(AttendanceScanEvent $event): void
    {
        $payload = $event->payload;

        // Duplicate scans do not trigger parent notifications
        if (! empty($payload['is_duplicate'])) {
            return;
        }

        $studentId   = $payload['student_id']   ?? null;
        $studentName = $payload['student_name']  ?? 'your child';
        $scanType    = $payload['type']          ?? 'in';
        $scanTime    = $payload['scan_time']     ?? now()->toIso8601String();
        $gateKey     = $payload['gate_location'] ?? null;

        if (! $studentId) return;

        // ── Resolve gate label ────────────────────────────────────────────────
        $gateLocations = config('student_attendance.gate_locations', []);
        $gateLabel     = $gateKey ? ($gateLocations[$gateKey] ?? $gateKey) : '';

        // ── Format scan time ──────────────────────────────────────────────────
        try {
            $formattedTime = Carbon::parse($scanTime)
                ->setTimezone(config('app.timezone', 'Asia/Manila'))
                ->format('F j, Y — g:i:s A');
        } catch (Throwable) {
            $formattedTime = $scanTime;
        }

        // ── Load parents for this student ─────────────────────────────────────
        $parents = DB::table('student_parent_contact as spc')
            ->join('parent_contacts as pc', 'pc.id', '=', 'spc.parent_contact_id')
            ->where('spc.student_id', $studentId)
            ->select('pc.*')
            ->get();

        if ($parents->isEmpty()) return;

        // ── Notify each parent ────────────────────────────────────────────────
        foreach ($parents as $parent) {
            // Email notification
            if ($parent->notify_email && ! empty($parent->email)) {
                try {
                    Mail::to($parent->email)->send(new StudentAttendanceMail(
                        parentName:        $parent->name,
                        studentName:       $studentName,
                        scanType:          $scanType,
                        scanTimeFormatted: $formattedTime,
                        gateLocation:      $gateLabel,
                    ));
                } catch (Throwable $e) {
                    Log::warning('StudentAttendance: failed to send email to parent.', [
                        'parent_id' => $parent->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            // FCM push notification
            if ($parent->notify_push && ! empty($parent->fcm_device_token)) {
                $title = $scanType === 'in'
                    ? "{$studentName} has arrived at school"
                    : "{$studentName} has left school";

                $body = $scanType === 'in'
                    ? "Time In recorded at {$formattedTime}" . ($gateLabel ? " ({$gateLabel})" : '')
                    : "Time Out recorded at {$formattedTime}" . ($gateLabel ? " ({$gateLabel})" : '');

                $this->fcm->send(
                    token: $parent->fcm_device_token,
                    title: $title,
                    body:  $body,
                    data:  [
                        'type'       => 'student_attendance',
                        'student_id' => (string) $studentId,
                        'scan_type'  => $scanType,
                        'scan_time'  => $scanTime,
                    ],
                );
            }
        }
    }
}
