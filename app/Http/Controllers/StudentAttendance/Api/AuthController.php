<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/mobile/login
     *
     * Authenticate a student or parent by email + password and return a
     * Sanctum token issued directly against their own model (StudentCredential
     * → Student, or ParentContact) — no row in the main Atlas `users` table.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $studentCredential = StudentCredential::where('email', $validated['email'])->first();

        if ($studentCredential) {
            return $this->loginStudent($studentCredential, $validated['password'], $validated['device_name']);
        }

        $parentContact = ParentContact::where('email', $validated['email'])->first();

        if ($parentContact && $parentContact->password) {
            return $this->loginParent($parentContact, $validated['password'], $validated['device_name']);
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    /**
     * POST /api/mobile/diagnostics/login-failure
     *
     * The login screen falls back to a generic "Login failed. Please try
     * again." message whenever it gets a real HTTP response it can't parse
     * into our usual JSON shape — a state that never appears in our own
     * request logs since whatever produced it wasn't our own JSON response.
     * The client reports what it actually saw here so it lands in
     * CloudWatch instead of being unattributable after the fact.
     */
    public function reportLoginFailure(Request $request): Response
    {
        $validated = $request->validate([
            'status_code'      => ['nullable', 'integer'],
            'response_snippet' => ['nullable', 'string', 'max:1000'],
            'platform'         => ['required', 'string', 'max:50'],
            'app_version'      => ['required', 'string', 'max:20'],
        ]);

        Log::warning('AtlasGo login failure diagnostic', [
            'status_code'      => $validated['status_code']      ?? null,
            'response_snippet' => $validated['response_snippet'] ?? null,
            'platform'         => $validated['platform'],
            'app_version'      => $validated['app_version'],
            'ip'               => $request->ip(),
        ]);

        return response()->noContent();
    }

    private function loginStudent(StudentCredential $credential, string $password, string $deviceName): JsonResponse
    {
        if (! Hash::check($password, $credential->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($credential->status === 'pending_verification') {
            return response()->json([
                'message'               => 'Please verify your email before signing in.',
                'requires_verification' => true,
                'email'                 => $credential->email,
            ], 403);
        }

        if ($credential->status === 'inactive') {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $student = Student::find($credential->student_id);

        if (! $student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $student->tokens()->where('name', $deviceName)->delete();
        $token = $student->createToken($deviceName, ['mobile'])->plainTextToken;

        return response()->json([
            'token'      => $token,
            'role'       => 'student',
            'user'       => ['id' => $student->id, 'name' => $student->full_name, 'email' => $credential->email],
            'student_id' => $student->id,
        ]);
    }

    private function loginParent(ParentContact $parentContact, string $password, string $deviceName): JsonResponse
    {
        if (! Hash::check($password, $parentContact->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($parentContact->status === 'pending_verification') {
            return response()->json([
                'message'               => 'Please verify your email before signing in.',
                'requires_verification' => true,
                'email'                 => $parentContact->email,
            ], 403);
        }

        if ($parentContact->status === 'inactive') {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $parentContact->tokens()->where('name', $deviceName)->delete();
        $token = $parentContact->createToken($deviceName, ['mobile'])->plainTextToken;

        return response()->json([
            'token'          => $token,
            'role'           => 'parent',
            'user'           => ['id' => $parentContact->id, 'name' => $parentContact->name, 'email' => $parentContact->email],
            'parent_contact' => ['id' => $parentContact->id],
        ]);
    }

    /**
     * GET /api/mobile/notification-preferences
     * Returns the parent's current push/email notification settings.
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $contact = $request->user();

        return response()->json([
            'notify_push'  => (bool) ($contact?->notify_push ?? true),
            'notify_email' => (bool) ($contact?->notify_email ?? true),
        ]);
    }

    /**
     * PUT /api/mobile/notification-preferences
     * Updates the parent's push/email notification settings.
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notify_push'  => ['required', 'boolean'],
            'notify_email' => ['required', 'boolean'],
        ]);

        $request->user()->update($validated);

        return response()->json(['message' => 'Preferences updated.']);
    }

    /**
     * DELETE /api/mobile/logout
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * PUT /api/mobile/fcm-token
     * Called by the Flutter app on startup or when FCM token refreshes.
     * Updates the authenticated recipient's FCM token — a parent updates
     * their own row directly; a student's token lives on their
     * `student_credentials` row (the `students` table itself is legacy/
     * read-only and never gets app-owned columns).
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        if ($user instanceof ParentContact) {
            $user->update([
                'fcm_device_token' => $validated['fcm_token'],
                'notify_push'      => true,
            ]);
        } elseif ($user instanceof Student) {
            $user->credential?->update(['fcm_device_token' => $validated['fcm_token']]);
        }

        return response()->json(['message' => 'FCM token updated.']);
    }
}
