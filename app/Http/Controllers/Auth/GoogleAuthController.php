<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth (server-side, no popup).
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->with(['hd' => 'crc.pshs.edu.ph'])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->with('error', 'Google sign-in failed. Please try again.');
        }

        $email = strtolower(trim($googleUser->getEmail() ?? ''));

        // Enforce PSHS-CRC domain
        if (! str_ends_with($email, '@crc.pshs.edu.ph')) {
            return redirect()->route('login')
                ->with('error', 'Only official PSHS-CRC accounts (@crc.pshs.edu.ph) are allowed.');
        }

        // Find or create user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name'     => $googleUser->getName(),
                'email'    => $email,
                'password' => Hash::make(Str::random(16)),
            ]);
        }

        // Block inactive accounts
        if (isset($user->status) && $user->status !== 'active') {
            return redirect()->route('login')
                ->with('error', 'Unable to log in. Contact MIS administrator.');
        }

        Auth::login($user, true);

        $role = $user->role ?? 'staff';

        return redirect($this->getRedirectPath($role));
    }

    /**
     * Legacy Firebase endpoint — kept for backwards compatibility.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name'  => 'required|string',
            'uid'   => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make(Str::random(16)),
                'firebase_uid' => $request->uid,
                'role'         => 'student',
            ]);
        }

        if (isset($user->status) && $user->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Unable to logged in, contact MIS administrator.'], 403);
        }

        Auth::login($user);

        $role         = $user->role ?? 'student';
        $redirectPath = $this->getRedirectPath($role);

        return response()->json([
            'success'     => true,
            'user'        => $user,
            'role'        => $role,
            'redirect_to' => $redirectPath,
        ]);
    }

    /**
     * Map role → redirect path.
     */
    protected function getRedirectPath(string $role): string
    {
        return match ($role) {
            'Administrator' => '/admin/dashboard',
            'teacher'       => '/teacher/home',
            'student'       => '/student/home',
            default         => '/dashboard',
        };
    }
}
