<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\StudentMobileLink;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/mobile/login
     *
     * Authenticate a system user and return a Sanctum token.
     * The parent app logs in using their school-system email + password.
     * On first login, a ParentContact record is auto-created/linked to their User.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (isset($user->status) && $user->status === 'pending_verification') {
            return response()->json([
                'message'              => 'Please verify your email before signing in.',
                'requires_verification' => true,
                'email'                => $user->email,
            ], 403);
        }

        if (isset($user->status) && $user->status === 'inactive') {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        // Revoke all existing tokens for this device to prevent accumulation
        $user->tokens()->where('name', $validated['device_name'])->delete();
        $token = $user->createToken($validated['device_name'], ['mobile'])->plainTextToken;

        // Detect student vs parent role
        $studentRole = Role::where('name', 'Student')->first();
        $isStudent   = $studentRole && (string) $user->role_id === (string) $studentRole->id;

        if ($isStudent) {
            $link = StudentMobileLink::where('user_id', $user->id)->first();

            return response()->json([
                'token'      => $token,
                'role'       => 'student',
                'user'       => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'student_id' => $link?->student_id,
            ]);
        }

        // Parent path — auto-create or retrieve parent contact
        $parentContact = ParentContact::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name'         => $user->name,
                'email'        => $user->email,
                'notify_email' => true,
                'notify_push'  => true,
            ]
        );

        return response()->json([
            'token'          => $token,
            'role'           => 'parent',
            'user'           => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'parent_contact' => ['id' => $parentContact->id],
        ]);
    }

    /**
     * GET /api/mobile/notification-preferences
     * Returns the parent's current push/email notification settings.
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $contact = ParentContact::where('user_id', $request->user()->id)->first();

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

        ParentContact::where('user_id', $request->user()->id)
            ->update($validated);

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
     * Updates both the User's linked ParentContact record.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        ParentContact::where('user_id', $user->id)->update([
            'fcm_device_token' => $validated['fcm_token'],
            'notify_push'      => true,
        ]);

        return response()->json(['message' => 'FCM token updated.']);
    }
}
