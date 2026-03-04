<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class GoogleAuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validate request
        $request->validate([
            'email' => 'required|email',
            'name'  => 'required|string',
            'uid'   => 'required|string', // Firebase UID
        ]);

        // 2. Find or create user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make(Str::random(16)), // dummy password
                'firebase_uid' => $request->uid, // optional
                'role'         => 'student',    // default role
            ]);
        }

        // 3. Prevent inactive accounts from logging in via Google SSO
        if (isset($user->status) && $user->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Unable to logged in, contact MIS administrator.'], 403);
        }

        // 4. Login user
        Auth::login($user);

        // 4. Get role + redirect path
        $role = $user->role ?? 'student';
        $redirectPath = $this->getRedirectPath($role);

        // 5. Return JSON with role & redirect
        return response()->json([
            'success'     => true,
            'user'        => $user,
            'role'        => $role,
            'redirect_to' => $redirectPath,
        ]);
    }

    /**
     * Map role → redirect path
     */
    protected function getRedirectPath(string $role): string
    {
        return match ($role) {
            'Administrator'   => '/admin/dashboard',
            'teacher' => '/teacher/home',
            'student' => '/student/home',
            default   => '/dashboard',
        };
    }
}
