<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\JobItem;
use App\Models\Placement;
use App\Models\User;
use App\Models\WFHAccomplishment;
use App\Models\WFHAttendance;
use App\Policies\Recruitment\ApplicationPolicy;
use App\Policies\Recruitment\JobItemPolicy;
use App\Policies\Recruitment\PlacementPolicy;
use App\Policies\WFHAccomplishmentPolicy;
use App\Policies\WFHAttendancePolicy;
use Illuminate\Support\Facades\Gate;
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
        // ── WFH Policies ──────────────────────────────────────────────────────
        Gate::policy(WFHAttendance::class, WFHAttendancePolicy::class);
        Gate::policy(WFHAccomplishment::class, WFHAccomplishmentPolicy::class);

        // ── Recruitment Policies ───────────────────────────────────────────────
        Gate::policy(JobItem::class, JobItemPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Placement::class, PlacementPolicy::class);

        // ── Recruitment permission gates (used by controllers via authorize()) ─
        foreach ([
            'recruitment.view',
            'recruitment.manage',
            'recruitment.publish',
            'recruitment.apply',
            'recruitment.evaluate',
            'recruitment.rank',
            'recruitment.approve',
            'recruitment.onboarding',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── L&D permission gates ───────────────────────────────────────────────
        foreach ([
            'lnd.view',
            'lnd.create',
            'lnd.edit',
            'lnd.delete',
            'lnd.approve',
            'lnd.evaluate',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── Rewards & Recognition permission gates ─────────────────────────────
        foreach ([
            'rewards.view',
            'rewards.nominate',
            'rewards.evaluate',
            'rewards.approve',
            'rewards.manage',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Student data management — restricted to Administrators only
        Gate::define('manage-students', fn (User $user) => $user->hasRole('Administrator'));

        // ── Payroll permission gates ───────────────────────────────────────────
        foreach ([
            'payroll.view',
            'payroll.process',
            'payroll.approve',
            'payroll.manage',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── HR permission gates ────────────────────────────────────────────────
        foreach ([
            'hr.leave.view',
            'hr.leave.file',
            'hr.leave.approve',
            'hr.dtr.view',
            'hr.dtr.manage',
            'hr.biometric.manage',
            'hr.employee.view',
            'hr.employee.manage',
            'hr.employees.view',
            'hr.employees.manage',
            'hr.schedule.view',
            'hr.schedule.manage',
            'hr.attendance.view',
            'hr.pds.view',
            'hr.pds.manage',
            'hr.gatepass.view',
            'hr.gatepass.create',
            'hr.gatepass.approve',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

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
