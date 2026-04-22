<?php

use App\Http\Controllers\FacultyLoading\AiDashboardController;
use App\Http\Controllers\FacultyLoading\FacultyListController;
use App\Http\Controllers\FacultyLoading\AutoAssignmentController;
use App\Http\Controllers\FacultyLoading\AutoScheduleController;
use App\Http\Controllers\FacultyLoading\LoadBalancingController;
use App\Http\Controllers\FacultyLoading\ClassroomController;
use App\Http\Controllers\FacultyLoading\SalaryScheduleController;
use App\Http\Controllers\FacultyLoading\ClassScheduleController;
use App\Http\Controllers\FacultyLoading\CommitteeAssignmentController;
use App\Http\Controllers\FacultyLoading\FacultyLoadController;
use App\Http\Controllers\FacultyLoading\LoadAssignmentController;
use App\Http\Controllers\FacultyLoading\OverloadComputationController;
use App\Http\Controllers\FacultyLoading\ReportController;
use App\Http\Controllers\FacultyLoading\ResearchAdvisoryController;
use App\Http\Controllers\FacultyLoading\SchoolYearController;
use App\Http\Controllers\FacultyLoading\SectionController;
use App\Http\Controllers\FacultyLoading\SubjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Faculty Loading Module Routes
|--------------------------------------------------------------------------
|
| Role/permission scopes:
|
|  1. Faculty          /faculty-loading/my-load         — view own load
|  2. CID / AUH        /faculty-loading                 — manage loads & schedules
|  3. Campus Director  /faculty-loading/overloads        — approve overloads
|  4. Admin/CID        /faculty-loading/catalog/*       — subjects, classrooms, school years
|
*/

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('faculty-loading')
    ->name('faculty-loading.')
    ->group(function () {

        // ══════════════════════════════════════════════════════════════════════
        // 1. Faculty — view own load
        // ══════════════════════════════════════════════════════════════════════
        Route::middleware('permission:faculty_loading.view_own')
            ->group(function () {
                Route::get('/my-load', [FacultyLoadController::class, 'myLoad'])->name('my-load');
            });

        // ══════════════════════════════════════════════════════════════════════
        // 2. CID/AUH — view all loads + manage schedules, assignments, sections
        // ══════════════════════════════════════════════════════════════════════
        Route::middleware('permission:faculty_loading.view')
            ->group(function () {
                Route::get('/', [FacultyLoadController::class, 'index'])->name('index');
            });

        Route::middleware('permission:faculty_loading.manage')
            ->group(function () {
                // ── Faculty List ────────────────────────────────────────────────
                Route::get('/faculty-list', [FacultyListController::class, 'index'])->name('faculty-list');
                Route::put('/faculty-list/{id}', [FacultyListController::class, 'update'])->name('faculty-list.update');

                // ── AI Optimization Dashboard ───────────────────────────────────
                Route::get('/ai-dashboard', [AiDashboardController::class, 'index'])->name('ai-dashboard');

                // ── Auto Assignment ─────────────────────────────────────────────
                Route::prefix('auto-assign')->name('auto-assign.')->group(function () {
                    Route::get('/preview', [AutoAssignmentController::class, 'preview'])->name('preview');
                    Route::post('/apply',  [AutoAssignmentController::class, 'apply'])->name('apply');
                });

                // ── Load Balancing Optimization ─────────────────────────────────
                Route::prefix('load-balance')->name('load-balance.')->group(function () {
                    Route::get('/',          [LoadBalancingController::class, 'index'])->name('index');
                    Route::get('/analysis',  [LoadBalancingController::class, 'analysis'])->name('analysis');
                    Route::post('/suggest',  [LoadBalancingController::class, 'suggest'])->name('suggest');
                    Route::post('/apply',    [LoadBalancingController::class, 'apply'])->name('apply');
                });

                // ── AI Auto Schedule Generator ──────────────────────────────────
                Route::prefix('auto-schedule')->name('auto-schedule.')->group(function () {
                    Route::get('/',                             [AutoScheduleController::class, 'index'])->name('index');
                    Route::post('/generate',                    [AutoScheduleController::class, 'generate'])->name('generate');
                    Route::get('/jobs',                         [AutoScheduleController::class, 'jobs'])->name('jobs');
                    Route::get('/jobs/{aiScheduleJob}',         [AutoScheduleController::class, 'showJob'])->name('jobs.show');
                    Route::post('/jobs/{aiScheduleJob}/apply',  [AutoScheduleController::class, 'apply'])->name('jobs.apply');
                });

                // Schedules
                Route::prefix('schedules')->name('schedules.')->group(function () {
                    Route::get('/',                          [ClassScheduleController::class, 'index'])->name('index');
                    Route::post('/validate',                 [ClassScheduleController::class, 'validateSchedule'])->name('validate');
                    Route::post('/',                         [ClassScheduleController::class, 'store'])->name('store');
                    Route::put('/{classSchedule}',           [ClassScheduleController::class, 'update'])->name('update');
                    Route::delete('/{classSchedule}',        [ClassScheduleController::class, 'destroy'])->name('destroy');
                });

                // Load Assignments
                Route::prefix('assignments')->name('assignments.')->group(function () {
                    Route::get('/',                         [LoadAssignmentController::class, 'index'])->name('index');
                    Route::post('/',                        [LoadAssignmentController::class, 'store'])->name('store');
                    Route::put('/{loadAssignment}',         [LoadAssignmentController::class, 'update'])->name('update');
                    Route::delete('/{loadAssignment}',      [LoadAssignmentController::class, 'destroy'])->name('destroy');
                });

                // Research Advisories
                Route::prefix('research-advisories')->name('research-advisories.')->group(function () {
                    Route::get('/',                         [ResearchAdvisoryController::class, 'index'])->name('index');
                    Route::post('/',                        [ResearchAdvisoryController::class, 'store'])->name('store');
                    Route::put('/{researchAdvisory}',       [ResearchAdvisoryController::class, 'update'])->name('update');
                    Route::delete('/{researchAdvisory}',    [ResearchAdvisoryController::class, 'destroy'])->name('destroy');
                });

                // Committee Assignments
                Route::prefix('committee-assignments')->name('committee-assignments.')->group(function () {
                    Route::get('/',                                 [CommitteeAssignmentController::class, 'index'])->name('index');
                    Route::get('/compliance',                       [CommitteeAssignmentController::class, 'compliance'])->name('compliance');
                    Route::post('/',                                [CommitteeAssignmentController::class, 'store'])->name('store');
                    Route::put('/{committeeAssignment}',            [CommitteeAssignmentController::class, 'update'])->name('update');
                    Route::delete('/{committeeAssignment}',         [CommitteeAssignmentController::class, 'destroy'])->name('destroy');
                });

                // Sections
                Route::prefix('sections')->name('sections.')->group(function () {
                    Route::get('/',                [SectionController::class, 'index'])->name('index');
                    Route::post('/',               [SectionController::class, 'store'])->name('store');
                    Route::put('/{section}',       [SectionController::class, 'update'])->name('update');
                    Route::delete('/{section}',    [SectionController::class, 'destroy'])->name('destroy');
                });
            });

        // ══════════════════════════════════════════════════════════════════════
        // 3. Campus Director — overload approval
        // ══════════════════════════════════════════════════════════════════════
        Route::middleware('permission:faculty_loading.approve')
            ->group(function () {
                // FacultyLoad-level overload flag + lock control
                Route::post('/{facultyLoad}/approve-overload',
                    [FacultyLoadController::class, 'approveOverload']
                )->name('approve-overload');
                Route::post('/{facultyLoad}/lock',
                    [FacultyLoadController::class, 'lockLoad']
                )->name('lock');
                Route::post('/{facultyLoad}/unlock',
                    [FacultyLoadController::class, 'unlockLoad']
                )->name('unlock');

                // Overload Computations (PHTR + pay)
                Route::prefix('overload-computations')->name('overload-computations.')->group(function () {
                    Route::get('/',                                           [OverloadComputationController::class, 'index'])->name('index');
                    Route::post('/preview',                                   [OverloadComputationController::class, 'preview'])->name('preview');
                    Route::post('/bulk-compute',                              [OverloadComputationController::class, 'bulkCompute'])->name('bulk-compute');
                    Route::post('/',                                          [OverloadComputationController::class, 'store'])->name('store');
                    Route::post('/{overloadComputation}/approve',             [OverloadComputationController::class, 'approve'])->name('approve');
                    Route::post('/{overloadComputation}/mark-paid',           [OverloadComputationController::class, 'markPaid'])->name('mark-paid');
                });

                // Salary Schedule (DBM SSL lookup & management)
                Route::prefix('salary-schedules')->name('salary-schedules.')->group(function () {
                    Route::get('/',                          [SalaryScheduleController::class, 'index'])->name('index');
                    Route::get('/lookup',                    [SalaryScheduleController::class, 'lookup'])->name('lookup');
                    Route::post('/',                         [SalaryScheduleController::class, 'store'])->name('store');
                    Route::put('/{salarySchedule}',          [SalaryScheduleController::class, 'update'])->name('update');
                    Route::post('/activate',                 [SalaryScheduleController::class, 'activate'])->name('activate');
                });
            });

        // ══════════════════════════════════════════════════════════════════════
        // 4. Reports — load summary + overload pay (CID, Campus Director)
        // ══════════════════════════════════════════════════════════════════════
        Route::middleware('permission:faculty_loading.reports')
            ->prefix('reports')->name('reports.')->group(function () {
                Route::get('/loads',               [ReportController::class, 'loads'])->name('loads');
                Route::get('/loads/export',        [ReportController::class, 'exportLoads'])->name('loads.export');
                Route::get('/overload-pay',        [ReportController::class, 'overloadPay'])->name('overload-pay');
                Route::get('/overload-pay/export', [ReportController::class, 'exportOverloadPay'])->name('overload-pay.export');
            });

        // ══════════════════════════════════════════════════════════════════════
        // 5. Catalog management — subjects, classrooms, school years
        // ══════════════════════════════════════════════════════════════════════

        // Subjects
        Route::middleware('permission:faculty_loading.subjects')
            ->prefix('subjects')->name('subjects.')->group(function () {
                Route::get('/',             [SubjectController::class, 'index'])->name('index');
                Route::post('/',            [SubjectController::class, 'store'])->name('store');
                Route::put('/{subject}',    [SubjectController::class, 'update'])->name('update');
                Route::delete('/{subject}', [SubjectController::class, 'destroy'])->name('destroy');
            });

        // Classrooms
        Route::middleware('permission:faculty_loading.classrooms')
            ->prefix('classrooms')->name('classrooms.')->group(function () {
                Route::get('/',               [ClassroomController::class, 'index'])->name('index');
                Route::post('/',              [ClassroomController::class, 'store'])->name('store');
                Route::put('/{classroom}',    [ClassroomController::class, 'update'])->name('update');
                Route::delete('/{classroom}', [ClassroomController::class, 'destroy'])->name('destroy');
            });

        // School Years & Academic Terms
        Route::middleware('permission:faculty_loading.school_year')
            ->prefix('school-years')->name('school-years.')->group(function () {
                Route::get('/',                            [SchoolYearController::class, 'index'])->name('index');
                Route::post('/',                           [SchoolYearController::class, 'store'])->name('store');
                Route::put('/{schoolYear}',                [SchoolYearController::class, 'update'])->name('update');
                Route::delete('/{schoolYear}',             [SchoolYearController::class, 'destroy'])->name('destroy');

                // Nested academic terms
                Route::post('/{schoolYear}/terms',   [SchoolYearController::class, 'storeTerm'])->name('terms.store');
                Route::put('/terms/{term}',          [SchoolYearController::class, 'updateTerm'])->name('terms.update');
                Route::delete('/terms/{term}',       [SchoolYearController::class, 'destroyTerm'])->name('terms.destroy');
            });
    });
