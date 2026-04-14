<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
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

        if (isset($user->status) && $user->status === 'inactive') {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        // Auto-create or retrieve the parent contact linked to this user
        $parentContact = ParentContact::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name'         => $user->name,
                'email'        => $user->email,
                'notify_email' => true,
                'notify_push'  => true,
            ]
        );

        // Revoke all existing tokens for this device to prevent accumulation
        $user->tokens()->where('name', $validated['device_name'])->delete();

        $token = $user->createToken($validated['device_name'], ['mobile'])->plainTextToken;

        return response()->json([
            'token'          => $token,
            'user'           => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'parent_contact' => ['id' => $parentContact->id],
        ]);
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
