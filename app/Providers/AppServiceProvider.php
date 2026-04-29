<?php

namespace App\Providers;

use App\Events\AttendanceScanEvent;
use App\Listeners\StudentAttendance\NotifyParentsOnScan;
use App\Models\Application;
use App\Models\JobItem;
use App\Models\Placement;
use App\Models\User;
use App\Models\WFHAccomplishment;
use App\Models\WFHAttendance;
use App\Models\PPMP\Ppmp;
use App\Policies\PpmpPolicy;
use App\Policies\Recruitment\ApplicationPolicy;
use App\Policies\Recruitment\JobItemPolicy;
use App\Policies\Recruitment\PlacementPolicy;
use App\Policies\WFHAccomplishmentPolicy;
use App\Policies\WFHAttendancePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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
        // ── Password Policy ───────────────────────────────────────────────────
        // Enforce: min 10 chars, at least 1 letter, 1 number, 1 symbol
        Password::defaults(fn () =>
            Password::min(10)
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
        );

        // ── WFH Policies ──────────────────────────────────────────────────────
        Gate::policy(WFHAttendance::class, WFHAttendancePolicy::class);
        Gate::policy(WFHAccomplishment::class, WFHAccomplishmentPolicy::class);

        // ── Recruitment Policies ───────────────────────────────────────────────
        Gate::policy(Ppmp::class, PpmpPolicy::class);
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

        // ── Student Attendance permission gates ────────────────────────────────
        foreach ([
            'students.attendance.view',
            'students.attendance.scan',
            'students.attendance.manage',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── Student Attendance: notify parents on each gate scan ───────────────
        Event::listen(AttendanceScanEvent::class, NotifyParentsOnScan::class);

        // ── Payroll permission gates ───────────────────────────────────────────
        foreach ([
            'payroll.view',
            'payroll.process',
            'payroll.approve',
            'payroll.manage',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── Faculty Loading permission gates ──────────────────────────────────
        foreach ([
            'faculty_loading.view',
            'faculty_loading.view_own',
            'faculty_loading.manage',
            'faculty_loading.load_assignments',
            'faculty_loading.approve',
            'faculty_loading.reports',
            'faculty_loading.subjects',
            'faculty_loading.classrooms',
            'faculty_loading.school_year',
            'faculty_loading.setup',
            'faculty_loading.vacancies',
            'faculty_loading.training',
            'faculty_loading.training.verify',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── PPMP permission gates ──────────────────────────────────────────────
        foreach ([
            'ppmp.create',
            'ppmp.submit',
            'ppmp.review',
            'ppmp.approve',
            'ppmp.consolidate',
            'ppmp.export',
            'ppmp.view_all',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── SALN permission gates ──────────────────────────────────────────────
        foreach ([
            'saln.create',
            'saln.submit',
            'saln.view_all',
            'saln.review',
            'saln.approve',
            'saln.file',
        ] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // ── HR permission gates ────────────────────────────────────────────────
        foreach ([
            'hr.leave.view',
            'hr.leave.file',
            'hr.leave.approve',
            'hr.leave.credits.view',
            'hr.leave.credits.manage',
            'hr.leave.credits.service',
            'hr.leave.credits.reports',
            'hr.dtr.view',
            'hr.dtr.manage',
            'hr.biometric.manage',
            'dtr.view_own',
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

        // ── Org Structure permission gates ─────────────────────────────────────
        foreach ([
            'org.view',
            'org.view_all',
            'org.units.create',
            'org.units.update',
            'org.units.delete',
            'org.units.manage',
            'org.assign',
            'org.assign.manage',
            'org.heads.manage',
            'org.versions.view',
            'org.versions.manage',
            'org.export',
            'org.reports',
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
