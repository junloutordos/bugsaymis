<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceKioskOperator;
use App\Services\StudentAttendance\KioskAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class KioskAccessController extends Controller
{
    public function __construct(private KioskAccessService $accessService) {}

    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operator_id' => ['required', 'integer'],
            'pin' => ['required', 'digits:6'],
        ]);

        /** @var StudentAttendanceDevice $device */
        $device = $request->attributes->get('studentAttendanceDevice');
        $rateKey = "attendance-kiosk-pin:{$device->id}:{$validated['operator_id']}";
        $maxAttempts = (int) config('student_attendance.kiosk_pin_attempts', 5);

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many incorrect attempts. Please wait before trying again.',
                'retry_after' => RateLimiter::availableIn($rateKey),
            ], 429);
        }

        $credential = StudentAttendanceKioskOperator::with('user.roles')
            ->where('user_id', $validated['operator_id'])
            ->where('is_active', true)
            ->first();

        $operator = $credential?->user;
        $allowed = $operator
            && $operator->status !== 'inactive'
            && $operator->hasRole('Security Guard')
            && Hash::check($validated['pin'], $credential->pin_hash);

        if (! $allowed) {
            RateLimiter::hit(
                $rateKey,
                (int) config('student_attendance.kiosk_pin_lockout_minutes', 15) * 60,
            );
            $this->accessService->recordFailedAttempt($request, $device, $operator?->id);

            return response()->json([
                'message' => 'The selected guard or PIN is incorrect.',
                'attempts_remaining' => max(0, $maxAttempts - RateLimiter::attempts($rateKey)),
            ], 422);
        }

        RateLimiter::clear($rateKey);
        $this->accessService->unlock($request, $device, $operator);

        return response()->json([
            'status' => 'ok',
            'operator' => ['id' => $operator->id, 'name' => $operator->name, 'is_administrator' => false],
        ]);
    }

    public function lock(Request $request): JsonResponse
    {
        /** @var StudentAttendanceDevice $device */
        $device = $request->attributes->get('studentAttendanceDevice');
        $this->accessService->lock($request, $device);

        return response()->json(['status' => 'ok']);
    }

    public function finishSetup(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student-attendance.kiosk');
    }
}
