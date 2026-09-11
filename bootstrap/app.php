<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/ams.php',
        ],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies so HTTPS is detected correctly behind Cloudflare → ALB
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\AtlasRequestStatsMiddleware::class,
            \App\Http\Middleware\EnsureActingAsWindowValid::class,
        ]);

        // Register custom middleware
        $middleware->alias([
            'role'          => \App\Http\Middleware\RoleMiddleware::class,
            'permission'    => \App\Http\Middleware\CheckPermission::class,
            'pshs.email'    => \App\Http\Middleware\EnsurePshsEmail::class,
            'student.portal'=> \App\Http\Middleware\StudentPortalMiddleware::class,
            'ict-agent'     => \App\Http\Middleware\EnsureAtlasSentinelDevice::class,
            'attendance.device' => \App\Http\Middleware\EnsureStudentAttendanceDevice::class,
            'attendance.device-mode' => \App\Http\Middleware\EnsureStudentAttendanceDeviceMode::class,
            'attendance.photo' => \App\Http\Middleware\EnsureStudentAttendancePhotoAccess::class,
            'attendance.scanner' => \App\Http\Middleware\EnsureStudentAttendanceScannerAccess::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia requests hitting a 403 (AuthorizationException from $this->authorize(),
        // a FormRequest::authorize() failure, a policy denial, or the `permission:` middleware's
        // abort(403, ...)) would otherwise get Laravel's raw HTML error page. Inertia's
        // router.post()/put()/etc. has no Inertia-formatted response to work with in that case,
        // so it forces a full-page browser navigation to that HTML page — which looks to the
        // user like the form silently did nothing ("it's not saving"). Redirect back with a
        // flashed error instead so the SPA experience stays intact.
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia')) {
                return back()->with('error', $e->getMessage() ?: 'You do not have permission to perform this action.');
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 403 && $request->header('X-Inertia')) {
                return back()->with('error', $e->getMessage() ?: 'You do not have permission to perform this action.');
            }
        });
    })->create();
