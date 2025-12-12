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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roles
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role) {
            abort(403, 'Unauthorized');
        }

        // Accept both comma and pipe separators
        $rolesArray = preg_split('/[,\|]/', $roles);

        if (! in_array($user->role->name, $rolesArray)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }

}
