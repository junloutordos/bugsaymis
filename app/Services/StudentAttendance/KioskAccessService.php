<?php

namespace App\Services\StudentAttendance;

use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceKioskAccessLog;
use App\Models\User;
use Illuminate\Http\Request;

class KioskAccessService
{
    public const OPERATOR_ID = 'attendance_kiosk.operator_id';
    public const DEVICE_ID = 'attendance_kiosk.device_id';
    public const UNLOCKED_AT = 'attendance_kiosk.unlocked_at';
    public const LAST_ACTIVITY_AT = 'attendance_kiosk.last_activity_at';

    public function currentOperator(
        Request $request,
        StudentAttendanceDevice $device,
        bool $touch = false,
    ): ?User {
        $authenticatedUser = $request->user();

        if ($authenticatedUser?->isSuperAdmin()) {
            return $authenticatedUser;
        }

        $operatorId = (int) $request->session()->get(self::OPERATOR_ID, 0);
        $deviceId = (int) $request->session()->get(self::DEVICE_ID, 0);
        $unlockedAt = (int) $request->session()->get(self::UNLOCKED_AT, 0);
        $lastActivityAt = (int) $request->session()->get(self::LAST_ACTIVITY_AT, 0);

        if (! $operatorId || $deviceId !== $device->id || ! $unlockedAt || ! $lastActivityAt) {
            return null;
        }

        $now = now()->timestamp;
        $absoluteLimit = (int) config('student_attendance.kiosk_session_hours', 12) * 3600;
        $idleLimit = (int) config('student_attendance.kiosk_background_timeout_minutes', 5) * 60;

        if (($now - $unlockedAt) > $absoluteLimit || ($now - $lastActivityAt) > $idleLimit) {
            $this->recordAccess($request, $device, $operatorId, 'expired', 'session_timeout');
            $this->clearSession($request);

            return null;
        }

        $operator = User::with(['roles', 'attendanceKioskCredential'])
            ->find($operatorId);

        if (! $operator
            || $operator->status === 'inactive'
            || ! $operator->hasRole('Security Guard')
            || ! $operator->attendanceKioskCredential?->is_active) {
            $this->recordAccess($request, $device, $operatorId, 'expired', 'access_disabled');
            $this->clearSession($request);

            return null;
        }

        if ($touch) {
            $request->session()->put(self::LAST_ACTIVITY_AT, $now);
        }

        return $operator;
    }

    public function unlock(Request $request, StudentAttendanceDevice $device, User $operator): void
    {
        $request->session()->regenerate();
        $now = now()->timestamp;

        $request->session()->put([
            self::OPERATOR_ID => $operator->id,
            self::DEVICE_ID => $device->id,
            self::UNLOCKED_AT => $now,
            self::LAST_ACTIVITY_AT => $now,
        ]);

        $this->recordAccess($request, $device, $operator->id, 'unlocked');
    }

    public function lock(Request $request, StudentAttendanceDevice $device, string $reason = 'end_shift'): void
    {
        $operatorId = (int) $request->session()->get(self::OPERATOR_ID, 0);

        if ($operatorId) {
            $this->recordAccess($request, $device, $operatorId, 'locked', $reason);
        }

        $this->clearSession($request);
        $request->session()->regenerateToken();
    }

    public function recordFailedAttempt(Request $request, StudentAttendanceDevice $device, ?int $userId): void
    {
        $this->recordAccess($request, $device, $userId, 'failed', 'invalid_credentials');
    }

    private function clearSession(Request $request): void
    {
        $request->session()->forget([
            self::OPERATOR_ID,
            self::DEVICE_ID,
            self::UNLOCKED_AT,
            self::LAST_ACTIVITY_AT,
        ]);
    }

    private function recordAccess(
        Request $request,
        StudentAttendanceDevice $device,
        ?int $userId,
        string $event,
        ?string $reason = null,
    ): void {
        StudentAttendanceKioskAccessLog::create([
            'kiosk_device_id' => $device->id,
            'user_id' => $userId,
            'event' => $event,
            'reason' => $reason,
            'ip_hash' => $request->ip()
                ? hash('sha256', config('app.key').'|'.$request->ip())
                : null,
            'occurred_at' => now(),
        ]);
    }
}
