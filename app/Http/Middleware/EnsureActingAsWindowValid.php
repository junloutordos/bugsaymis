<?php

namespace App\Http\Middleware;

use App\Services\HR\ActingAsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActingAsWindowValid
{
    public function __construct(private ActingAsService $actingAs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $substitution = $this->actingAs->currentSubstitution($request);

        if (! $substitution) {
            return $next($request);
        }

        $valid = $substitution->status === 'approved' && $substitution->isWithinWindow();

        if ($valid) {
            return $next($request);
        }

        $reason = $substitution->status === 'revoked' ? 'revoked' : 'expired';
        $this->actingAs->exit($request, $reason);

        return redirect()->route('dashboard')->with('error', 'Your acting-as access window has ended.');
    }
}
