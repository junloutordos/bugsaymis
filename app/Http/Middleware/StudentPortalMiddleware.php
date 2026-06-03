<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentPortalMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('student_pisaysystemID')) {
            return redirect()->route('student-portal.login')
                ->with('error', 'Please sign in to access the student portal.');
        }

        return $next($request);
    }
}
