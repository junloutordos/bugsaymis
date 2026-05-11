<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NetworkScannerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $configuredToken = config('services.network_scanner.token');

        if (empty($configuredToken)) {
            return response()->json(['message' => 'Scanner token not configured on server.'], 503);
        }

        $provided = $request->bearerToken();

        if (! $provided || ! hash_equals($configuredToken, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
