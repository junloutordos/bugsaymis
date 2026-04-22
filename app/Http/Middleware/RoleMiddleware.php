<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage:
     *   ->middleware('role:Administrator')
     *   ->middleware('role:Administrator|HR')   // any of these roles
     *
     * SuperAdmins (Administrator) always pass.
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $allowed = preg_split('/[,\|]/', $roles);

        if (! $user->hasAnyRole($allowed)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
