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
        ]);

        // Register custom middleware
        $middleware->alias([
            'role'          => \App\Http\Middleware\RoleMiddleware::class,
            'permission'    => \App\Http\Middleware\CheckPermission::class,
            'pshs.email'    => \App\Http\Middleware\EnsurePshsEmail::class,
            'student.portal'=> \App\Http\Middleware\StudentPortalMiddleware::class,
            'ict-agent'     => \App\Http\Middleware\EnsureAtlasSentinelDevice::class,
            'attendance.device' => \App\Http\Middleware\EnsureStudentAttendanceDevice::class,
            'attendance.scanner' => \App\Http\Middleware\EnsureStudentAttendanceScannerAccess::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
