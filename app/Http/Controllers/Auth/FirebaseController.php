<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Kreait\Firebase\Auth as FirebaseAuth;

class FirebaseController extends Controller
{
    public function login(\Illuminate\Http\Request $request, FirebaseAuth $firebaseAuth)
    {
        $verifiedIdToken = $firebaseAuth->verifyIdToken($request->token);
        $email = $verifiedIdToken->claims()->get('email');

        // Restrict domain
        if (!str_ends_with($email, '@crc.pshs.edu.ph')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $verifiedIdToken->claims()->get('name'), 'account_type' => 'employee']
        );

        // Defense-in-depth: students/parents must only reach /student-portal
        // and AtlasGo — never the main Atlas web app, even if a legacy
        // users row still exists for them (pre-cleanup transition window).
        if (in_array($user->account_type, ['student', 'parent'], true)) {
            return response()->json(['error' => 'Student and parent accounts must use the Student Portal or the AtlasGo app.'], 403);
        }

        // Prevent inactive accounts from logging in via Firebase SSO
        if (isset($user->status) && $user->status !== 'active') {
            return response()->json(['error' => 'Unable to logged in, contact MIS administrator.'], 403);
        }

        Auth::login($user);

        return redirect()->intended('/dashboard');
    }
}
