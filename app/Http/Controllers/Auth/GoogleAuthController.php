<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $ip = request()->ip();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            $this->securityLog('warning', 'Socialite Google callback failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            return redirect()->route('login')
                ->with('error', 'Google sign-in failed. Please try again.');
        }

        $email = strtolower(trim($googleUser->getEmail() ?? ''));

        // Enforce PSHS-CRC domain
        if (! str_ends_with($email, '@crc.pshs.edu.ph')) {
            $this->securityLog('warning', 'Socialite login rejected: non-PSHS domain', ['email' => $email, 'ip' => $ip]);
            return redirect()->route('login')
                ->with('error', 'Only official PSHS-CRC accounts (@crc.pshs.edu.ph) are allowed.');
        }

        // Find or create user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name'         => $googleUser->getName(),
                'email'        => $email,
                'password'     => Hash::make(Str::random(16)),
                'account_type' => 'employee',
            ]);
        }

        // Defense-in-depth: students/parents must only reach /student-portal
        // and AtlasGo — never the main Atlas web app, even if a legacy
        // users row still exists for them (pre-cleanup transition window).
        if (in_array($user->account_type, ['student', 'parent'], true)) {
            $this->securityLog('warning', 'Socialite login rejected: student/parent account_type', ['email' => $email, 'ip' => $ip]);
            return redirect()->route('login')
                ->with('error', 'Student and parent accounts must use the Student Portal or the AtlasGo app.');
        }

        // Block inactive accounts
        if (isset($user->status) && $user->status !== 'active') {
            $this->securityLog('warning', 'Socialite login rejected: inactive account', ['email' => $email, 'ip' => $ip]);
            return redirect()->route('login')
                ->with('error', 'Unable to log in. Contact MIS administrator.');
        }

        Auth::login($user, true);
        $this->securityLog('info', 'Socialite login success', ['email' => $email, 'ip' => $ip, 'role' => $user->role ?? 'staff']);

        $this->flagSignatureSetupPrompt($user);
        $this->flagEmployeeIdSetupPrompt($user);

        $role = $user->role ?? 'staff';

        return redirect($this->getRedirectPath($role));
    }

    /**
     * Firebase popup callback — called by the frontend after Firebase signs in.
     * Security: enforces @crc.pshs.edu.ph domain, blocks inactive accounts,
     * and does NOT create accounts (admin must pre-create users).
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name'  => 'required|string',
            'uid'   => 'required|string',
        ]);

        $email = strtolower(trim($request->email));

        $ip = request()->ip();

        // Enforce PSHS-CRC domain
        if (! str_ends_with($email, '@crc.pshs.edu.ph')) {
            $this->securityLog('warning', 'Google login rejected: non-PSHS domain', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Only official PSHS-CRC accounts are allowed.'], 403);
        }

        // User must already exist — no auto-creation via this endpoint
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->securityLog('warning', 'Google login rejected: account not found', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Account not found. Contact MIS administrator.'], 403);
        }

        // Defense-in-depth: students/parents must only reach /student-portal
        // and AtlasGo — never the main Atlas web app.
        if (in_array($user->account_type, ['student', 'parent'], true)) {
            $this->securityLog('warning', 'Google login rejected: student/parent account_type', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Student and parent accounts must use the Student Portal or the AtlasGo app.'], 403);
        }

        if (isset($user->status) && $user->status !== 'active') {
            $this->securityLog('warning', 'Google login rejected: inactive account', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Unable to log in. Contact MIS administrator.'], 403);
        }

        Auth::login($user, true);
        $this->securityLog('info', 'Google login success', ['email' => $email, 'ip' => $ip, 'role' => $user->role ?? 'staff']);

        $this->flagSignatureSetupPrompt($user);
        $this->flagEmployeeIdSetupPrompt($user);

        $role         = $user->role ?? 'staff';
        $redirectPath = $this->getRedirectPath($role);

        return response()->json([
            'success'     => true,
            'redirect_to' => $redirectPath,
        ]);
    }

    /**
     * Flag the session so the next Inertia page render shows the digital
     * signature setup prompt — only while the user still has no signature
     * image or signing PIN. Consumed (pulled) by HandleInertiaRequests.
     */
    protected function flagSignatureSetupPrompt(User $user): void
    {
        if (empty($user->electronic_signature) || empty($user->signature_pin)) {
            session(['prompt_signature_setup' => true]);
        }
    }

    /**
     * Flag the session so the mandatory hire-year/month prompt shows on the
     * next Inertia page render — only while employee_idno_new is still
     * unset. This prompt cannot be dismissed until answered.
     */
    protected function flagEmployeeIdSetupPrompt(User $user): void
    {
        if (empty($user->employee_idno_new)) {
            session(['prompt_employee_id_setup' => true]);
        }
    }

    /**
     * Write to the security log channel, falling back to the default logger
     * if the security log file cannot be opened (e.g. wrong ownership from a
     * root-run cron job creating the daily file first).
     */
    protected function securityLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('security')->{$level}($message, $context);
        } catch (\Throwable $e) {
            Log::{$level}('[security] ' . $message, $context);
        }
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
