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
use App\Services\Atlas\Dyna\DynaToolRegistry;
use App\Services\Atlas\Dyna\Tools\GetHeadcountTool;
use App\Services\Atlas\Dyna\Tools\GetLeaveTrendsTool;
use App\Services\Atlas\Dyna\Tools\GetPerformanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetRequestsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetSatisfactionStatsTool;
use App\Services\Atlas\Dyna\Tools\GetAcademicsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetRecruitmentStatsTool;
use App\Services\Atlas\Dyna\Tools\GetFinanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetOperationsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetAttentionItemsTool;
use App\Services\Atlas\Dyna\Tools\GetDivisionScorecardTool;
use App\Services\Atlas\Dyna\Tools\GetFacultyLoadDistributionTool;
use App\Services\Atlas\Dyna\Tools\GetClassRecordComplianceTool;
use App\Services\Atlas\Dyna\Tools\GetTeacherAttendanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetEnrollmentStatusBreakdownTool;
use App\Services\Atlas\Dyna\Tools\GetGateAttendanceTrendTool;
use App\Services\Atlas\Dyna\Tools\GetLibraryStatsTool;
use App\Services\Atlas\Dyna\Tools\GetCompetitionsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetDisciplineCaseStatsTool;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Event;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // DynaToolRegistry takes an array of DynaTool instances — the container can't
        // auto-wire an array constructor param by type-hint alone, so it needs an
        // explicit binding listing every registered Dyna tool.
        $this->app->singleton(DynaToolRegistry::class, function ($app) {
            return new DynaToolRegistry([
                $app->make(GetHeadcountTool::class),
                $app->make(GetLeaveTrendsTool::class),
                $app->make(GetPerformanceStatsTool::class),
                $app->make(GetRequestsStatsTool::class),
                $app->make(GetSatisfactionStatsTool::class),
                $app->make(GetAcademicsStatsTool::class),
                $app->make(GetRecruitmentStatsTool::class),
                $app->make(GetFinanceStatsTool::class),
                $app->make(GetOperationsStatsTool::class),
                $app->make(GetAttentionItemsTool::class),
                $app->make(GetDivisionScorecardTool::class),
                $app->make(GetFacultyLoadDistributionTool::class),
                $app->make(GetClassRecordComplianceTool::class),
                $app->make(GetTeacherAttendanceStatsTool::class),
                $app->make(GetEnrollmentStatusBreakdownTool::class),
                $app->make(GetGateAttendanceTrendTool::class),
                $app->make(GetLibraryStatsTool::class),
                $app->make(GetCompetitionsStatsTool::class),
                $app->make(GetDisciplineCaseStatsTool::class),
                // Tasks 6-8 append their tools to this same array.
            ]);
        });
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

        // ── Dynamic permission gates ───────────────────────────────────────────
        // SuperAdmins bypass all checks. For everyone else, any ability that
        // matches a permission name in the DB is resolved via the user's
        // permission cache — no per-permission gate registration needed.
        // Policies registered above still run for their respective models.
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) return true;
            if ($user->hasPermission($ability)) return true;
            return null; // fall through to policies
        });

        // manage-students is Administrator-only and not a named DB permission
        Gate::define('manage-students', fn (User $user) => $user->isSuperAdmin());

        RateLimiter::for('student-attendance-scan', function (Request $request) {
            $deviceToken = $request->cookie(\App\Http\Middleware\EnsureStudentAttendanceDevice::COOKIE, 'unpaired');

            return Limit::perMinute(120)->by($request->user()?->id.'|'.hash('sha256', $deviceToken));
        });

        RateLimiter::for('student-attendance-directory', function (Request $request) {
            $deviceToken = $request->cookie(\App\Http\Middleware\EnsureStudentAttendanceDevice::COOKIE, 'unpaired');
            $operatorId = $request->session()->get(\App\Services\StudentAttendance\KioskAccessService::OPERATOR_ID)
                ?? $request->user()?->id
                ?? 'locked';

            return Limit::perMinute(45)->by($operatorId.'|'.hash('sha256', $deviceToken));
        });

        RateLimiter::for('student-attendance-contact-reveal', function (Request $request) {
            $deviceToken = $request->cookie(\App\Http\Middleware\EnsureStudentAttendanceDevice::COOKIE, 'unpaired');
            $operatorId = $request->session()->get(\App\Services\StudentAttendance\KioskAccessService::OPERATOR_ID)
                ?? $request->user()?->id
                ?? 'locked';

            return Limit::perMinute(15)->by($operatorId.'|'.hash('sha256', $deviceToken));
        });

        // ── Student Attendance: notify parents on each gate scan ───────────────
        Event::listen(AttendanceScanEvent::class, NotifyParentsOnScan::class);

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
            if ($model
                && ! ($model instanceof \App\Models\AuditLog)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceLog)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceDevice)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceKioskOperator)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceKioskAccessLog)) {
                AuditLogger::logModelEvent($model, 'created');
            }
        });

        Event::listen('eloquent.updated: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model
                && ! ($model instanceof \App\Models\AuditLog)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceLog)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceDevice)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceKioskOperator)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceKioskAccessLog)) {
                AuditLogger::logModelEvent($model, 'updated');
            }
        });

        Event::listen('eloquent.deleted: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model
                && ! ($model instanceof \App\Models\AuditLog)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceLog)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceDevice)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceKioskOperator)
                && ! ($model instanceof \App\Models\StudentAttendance\StudentAttendanceKioskAccessLog)) {
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
