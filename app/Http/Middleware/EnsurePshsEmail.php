<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePshsEmail
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Guard against missing email property; coerce to string to avoid type errors
        $email = $user->email ?? '';
        if ($user && !str_ends_with((string) $email, '@crc.pshs.edu.ph')) {
            // Logout and redirect if email domain is not allowed
            auth()->logout();

            return redirect('/login')
                ->withErrors(['email' => 'Access restricted to @crc.pshs.edu.ph accounts only.']);
        }

        return $next($request);
    }
}
