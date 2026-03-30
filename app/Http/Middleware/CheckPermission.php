<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   ->middleware('permission:wfh.view')
     *   ->middleware('permission:wfh.view|wfh.monitor')   // any of these
     *   ->middleware('permission:wfh.view,wfh.monitor')   // all of these
     *
     * Pipe  (|) = user needs ANY one of the listed permissions.
     * Comma (,) = user needs ALL of the listed permissions.
     *
     * SuperAdmins (Administrator role) bypass all checks.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // SuperAdmin bypasses every check
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Each $permission param may be pipe-separated (any-of group)
        foreach ($permissions as $permissionGroup) {
            $anyOf = explode('|', $permissionGroup);

            if (! $user->hasAnyPermission($anyOf)) {
                return $this->deny($request);
            }
        }

        return $next($request);
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson() || $request->header('X-Inertia')) {
            abort(403, 'You do not have permission to perform this action.');
        }

        abort(403, 'Unauthorized.');
    }
}
