<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Ensure preload tags for CSS use the correct `as` attribute so browsers
        // don't warn about preloaded stylesheets that are not used immediately.
        Vite::usePreloadTagAttributes(function ($src, $url, $chunk, $manifest) {
            if (str_ends_with($url, '.css')) {
                return ['as' => 'style'];
            }

            return [];
        });

        // Listen to Eloquent model events globally and record audit logs.
        Event::listen('eloquent.created: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model && !($model instanceof \App\Models\AuditLog)) {
                AuditLogger::logModelEvent($model, 'created');
            }
        });

        Event::listen('eloquent.updated: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model && !($model instanceof \App\Models\AuditLog)) {
                AuditLogger::logModelEvent($model, 'updated');
            }
        });

        Event::listen('eloquent.deleted: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model && !($model instanceof \App\Models\AuditLog)) {
                AuditLogger::logModelEvent($model, 'deleted');
            }
        });

        // Authentication events
        Event::listen(Login::class, function (Login $event) {
            AuditLogger::log([
                'action' => 'login',
                'auditable_type' => get_class($event->user),
                'auditable_id' => $event->user->getKey(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            AuditLogger::log([
                'action' => 'logout',
                'auditable_type' => $event->user ? get_class($event->user) : null,
                'auditable_id' => $event->user ? $event->user->getKey() : null,
            ]);
        });
    }
}
