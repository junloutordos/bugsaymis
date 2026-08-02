<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Atlas\Dyna\DynaGoogleClientFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DynaAuthController extends Controller
{
    public function __construct(private readonly DynaGoogleClientFactory $googleClientFactory) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        if (! $user->hasPermission('atlas.dyna.access')) {
            return response()->json(['message' => 'This account does not have Dyna access.'], 403);
        }

        $user->tokens()->where('name', $validated['device_name'])->delete();
        $token = $user->createToken($validated['device_name'], ['dyna'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function loginWithGoogle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $payload = $this->googleClientFactory->make()->verifyIdToken($validated['id_token']);
        } catch (\Throwable) {
            $payload = false;
        }

        if (! $payload || empty($payload['email'])) {
            return response()->json(['message' => 'Google sign-in could not be verified.'], 401);
        }

        $user = User::where('email', $payload['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'No Atlas Account found for this Google account.'], 404);
        }

        if (! $user->hasPermission('atlas.dyna.access')) {
            return response()->json(['message' => 'This account does not have Dyna access.'], 403);
        }

        $user->tokens()->where('name', $validated['device_name'])->delete();
        $token = $user->createToken($validated['device_name'], ['dyna'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }
}
