<?php

namespace App\Http\Middleware;

use App\Models\IctEquipmentDevice;
use Closure;
use Illuminate\Http\Request;

class EnsureIctAgentDevice
{
    /**
     * Reject any Sanctum token that doesn't belong to an ICT agent device —
     * a regular user's token must never be usable on agent endpoints, and
     * vice versa. $request->user() resolves to the specific device row tied
     * to the bearer token, so a device can only ever act as itself.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() instanceof IctEquipmentDevice) {
            abort(403, 'This endpoint is for ICT agent devices only.');
        }

        return $next($request);
    }
}
