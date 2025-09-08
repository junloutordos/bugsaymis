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
            ['name' => $verifiedIdToken->claims()->get('name')]
        );

        Auth::login($user);

        return redirect()->intended('/dashboard');
    }
}
