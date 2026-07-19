<?php

use App\Http\Controllers\FacultyLoading\AcademicUnitController;
use App\Http\Controllers\FacultyLoading\AiDashboardController;
use App\Http\Controllers\FacultyLoading\FacultyListController;
use App\Http\Controllers\FacultyLoading\AutoAssignmentController;
use App\Http\Controllers\FacultyLoading\AutoPlacementController;
use App\Http\Controllers\FacultyLoading\AutoScheduleController;
use App\Http\Controllers\FacultyLoading\SlotPlanController;
use App\Http\Controllers\FacultyLoading\DesignationController;
use App\Http\Controllers\FacultyLoading\FacultyVacancyController;
use App\Http\Controllers\FacultyLoading\LoadBalancingController;
use App\Http\Controllers\FacultyLoading\ClassroomController;
use App\Http\Controllers\FacultyLoading\SalaryScheduleController;
use App\Http\Controllers\FacultyLoading\ScheduleAnalyticsController;
use App\Http\Controllers\FacultyLoading\BellScheduleController;
use App\Http\Controllers\FacultyLoading\ClassScheduleController;
use App\Http\Controllers\FacultyLoading\ClassScheduleApprovalController;
use App\Http\Controllers\FacultyLoading\ClassScheduleSwapController;
use App\Http\Controllers\FacultyLoading\ScheduleVersionController;
use App\Http\Controllers\FacultyLoading\CommitteeAssignmentController;
use App\Http\Controllers\FacultyLoading\FacultyLoadController;
use App\Http\Controllers\FacultyLoading\LoadAssignmentController;
use App\Http\Controllers\FacultyLoading\LoadAssignmentVersionController;
use App\Http\Controllers\FacultyLoading\OverloadComputationController;
use App\Http\Controllers\FacultyLoading\ReportController;
use App\Http\Controllers\FacultyLoading\ResearchAdvisoryController;
use App\Http\Controllers\FacultyLoading\SchoolYearController;
use App\Http\Controllers\FacultyLoading\SectionController;
use App\Http\Controllers\FacultyLoading\SupervisoryController;
use App\Http\Controllers\FacultyLoading\SubjectController;
use App\Http\Controllers\FacultyLoading\TrainingRecordController;
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
                Route::get('/my-load',       [FacultyLoadController::class, 'myLoad'])->name('my-load');
                Route::get('/my-load/print', [FacultyLoadController::class, 'printMyLoad'])->name('my-load.print');
            });

        // My Faculty Schedule — everyone's personal calendar, managers included
        // (managers may not hold view_own, hence the OR).
        Route::middleware('permission:faculty_loading.view_own|faculty_loading.manage')
            ->get('/my-schedule', [ClassScheduleController::class, 'mySchedule'])->name('my-schedule');

        // Committee detail + task board + accomplishments/ratings — open to
        // chairpersons and members, not only admins; the controller enforces
        // the actor (membership gate in show(), owner check in
        // saveAccomplishment, hierarchical chair gate in rateAssignment).
        Route::middleware('permission:faculty_loading.view_own|faculty_loading.manage')
            ->prefix('committee-assignments')->name('committee-assignments.')->group(function () {
                Route::get('/committee/{committee}',                 [CommitteeAssignmentController::class, 'show'])->name('show');
                Route::post('/{committeeAssignment}/accomplishment', [CommitteeAssignmentController::class, 'saveAccomplishment'])->name('accomplishment');
                Route::post('/{committeeAssignment}/rate',           [CommitteeAssignmentController::class, 'rateAssignment'])->name('rate');
            });

        // ══════════════════════════════════════════════════════════════════════
        // 2. CID/AUH — view all loads + manage schedules, assignments, sections
        // ══════════════════════════════════════════════════════════════════════
        Route::middleware('permission:faculty_loading.view')
            ->group(function () {
                Route::get('/',                          [FacultyLoadController::class, 'index'])->name('index');
                Route::get('/print-batch',               [FacultyLoadController::class, 'printBatch'])->name('print-batch');
                Route::get('/{facultyLoad}/print',       [FacultyLoadController::class, 'print'])->name('print');
            });

        // Schedules calendar — CID/AUH manage everything; faculty_loading.view_own
        // holders get self-mode access to add non-teaching blocks to their own
        // calendar. Per-action capability checks live in ClassScheduleController.
        Route::middleware('permission:faculty_loading.manage|faculty_loading.view_own|faculty_loading.approve')
            ->prefix('schedules')->name('schedules.')->group(function () {
                Route::get('/',                   [ClassScheduleController::class, 'index'])->name('index');
                Route::get('/faculty/{faculty}/print', [ClassScheduleController::class, 'printFaculty'])->name('faculty.print');
                Route::post('/validate',          [ClassScheduleController::class, 'validateSchedule'])->name('validate');
                Route::post('/',                  [ClassScheduleController::class, 'store'])->name('store');
                Route::put('/{classSchedule}',    [ClassScheduleController::class, 'update'])->name('update');
                Route::delete('/{classSchedule}', [ClassScheduleController::class, 'destroy'])->name('destroy');
                Route::post('/{classSchedule}/swap-requests', [ClassScheduleSwapController::class, 'store'])->name('swap-requests.store');
                Route::post('/swap-requests/{swapRequest}/cancel', [ClassScheduleSwapController::class, 'cancel'])->name('swap-requests.cancel');
            });

        Route::middleware('permission:faculty_loading.manage')
            ->prefix('schedules/swap-requests')->name('schedules.swap-requests.')->group(function () {
                Route::get('/{swapRequest}/candidates', [ClassScheduleSwapController::class, 'candidates'])->name('candidates');
                Route::post('/{swapRequest}/apply', [ClassScheduleSwapController::class, 'apply'])->name('apply');
                Route::post('/{swapRequest}/decline', [ClassScheduleSwapController::class, 'decline'])->name('decline');
            });

        Route::middleware('permission:faculty_loading.manage')
            ->get('/schedules/sections/{section}/print', [ClassScheduleController::class, 'printSection'])
            ->name('schedules.sections.print');

        Route::middleware('permission:faculty_loading.manage')
            ->get('/schedules/print-batch', [ClassScheduleController::class, 'printBatchSchedules'])
            ->name('schedules.print-batch');

        // ── Schedule Balance Analytics (read-only) ─────────────────────────
        Route::middleware('permission:faculty_loading.manage|faculty_loading.approve')
            ->prefix('schedule-analytics')->name('schedule-analytics.')->group(function () {
                Route::get('/',     [ScheduleAnalyticsController::class, 'index'])->name('index');
                Route::get('/data', [ScheduleAnalyticsController::class, 'data'])->name('data');
            });

        // ── Bell Schedule editor (Administrator + CID Chief; role-gated in ctrl) ─
        Route::middleware('permission:faculty_loading.manage')
            ->prefix('bell-schedule')->name('bell-schedule.')->group(function () {
                Route::get('/',               [BellScheduleController::class, 'index'])->name('index');
                Route::post('/',              [BellScheduleController::class, 'update'])->name('update');
                Route::post('/reset',         [BellScheduleController::class, 'reset'])->name('reset');
                Route::post('/setting',       [BellScheduleController::class, 'updateSetting'])->name('setting.update');
                Route::post('/setting/reset', [BellScheduleController::class, 'resetSetting'])->name('setting.reset');
            });

        Route::post('/schedules/terms/{term}/submit', [ClassScheduleApprovalController::class, 'submit'])
            ->name('schedules.approval.submit');
        Route::post('/schedules/approvals/{batch}/approve', [ClassScheduleApprovalController::class, 'approve'])
            ->name('schedules.approval.approve');
        Route::post('/schedules/approvals/{batch}/return', [ClassScheduleApprovalController::class, 'returnForRevision'])
            ->name('schedules.approval.return');
        Route::post('/schedules/approvals/{batch}/conforme', [ClassScheduleApprovalController::class, 'conforme'])
            ->name('schedules.approval.conforme');

        // ── Schedule Versions (save/compare/restore drafts) ────────────────
        // Save/Restore/Delete are CID/admin-only, Compare is also open to OCD —
        // enforced per-action inside ScheduleVersionController.
        Route::middleware('permission:faculty_loading.manage|faculty_loading.approve')
            ->prefix('schedules/versions')->name('schedules.versions.')->group(function () {
                Route::get('/',                     [ScheduleVersionController::class, 'index'])->name('index');
                Route::post('/',                    [ScheduleVersionController::class, 'store'])->name('store');
                Route::post('/compare',              [ScheduleVersionController::class, 'compare'])->name('compare');
                Route::post('/{scheduleVersion}/restore', [ScheduleVersionController::class, 'restore'])->name('restore');
                Route::delete('/{scheduleVersion}',  [ScheduleVersionController::class, 'destroy'])->name('destroy');
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
                    Route::post('/jobs/{aiScheduleJob}/restore',[AutoScheduleController::class, 'restore'])->name('jobs.restore');

                    // ── Deterministic Slot Plan Generator (Phases 7–11) ────────
                    Route::get('/slot-plan',         [SlotPlanController::class, 'index'])->name('slot-plan.index');
                    Route::post('/slot-plan/preview',[SlotPlanController::class, 'preview'])->name('slot-plan.preview');
                });

                // ── Incremental auto-placement (Unplaced Subjects tray) ─────────
                Route::prefix('schedules/auto-place')->name('schedules.auto-place.')->group(function () {
                    Route::post('/preview',     [AutoPlacementController::class, 'preview'])->name('preview');
                    Route::post('/commit',      [AutoPlacementController::class, 'commit'])->name('commit');
                    Route::post('/suggestions', [AutoPlacementController::class, 'suggestions'])->name('suggestions');
                });

                // Load Assignments
                Route::prefix('assignments')->name('assignments.')->group(function () {
                    Route::get('/',                         [LoadAssignmentController::class, 'index'])->name('index');
                    Route::post('/',                        [LoadAssignmentController::class, 'store'])->name('store');
                    Route::post('/sync-loads',              [LoadAssignmentController::class, 'syncAllLoads'])->name('sync-loads');
                    Route::put('/{loadAssignment}',         [LoadAssignmentController::class, 'update'])->name('update');
                    Route::delete('/{loadAssignment}',      [LoadAssignmentController::class, 'destroy'])->name('destroy');
                });

                // Load Assignment Versions (save/compare/restore drafts)
                Route::prefix('assignments/versions')->name('assignments.versions.')->group(function () {
                    Route::get('/',                     [LoadAssignmentVersionController::class, 'index'])->name('index');
                    Route::post('/',                    [LoadAssignmentVersionController::class, 'store'])->name('store');
                    Route::post('/compare',              [LoadAssignmentVersionController::class, 'compare'])->name('compare');
                    Route::post('/{loadAssignmentVersion}/restore', [LoadAssignmentVersionController::class, 'restore'])->name('restore');
                    Route::delete('/{loadAssignmentVersion}',  [LoadAssignmentVersionController::class, 'destroy'])->name('destroy');
                });

                // Research Advisories
                Route::prefix('research-advisories')->name('research-advisories.')->group(function () {
                    Route::get('/',                         [ResearchAdvisoryController::class, 'index'])->name('index');
                    Route::get('/students',                 [ResearchAdvisoryController::class, 'studentsForGrade'])->name('students');
                    Route::post('/',                        [ResearchAdvisoryController::class, 'store'])->name('store');
                    Route::put('/{researchAdvisory}',       [ResearchAdvisoryController::class, 'update'])->name('update');
                    Route::delete('/{researchAdvisory}',    [ResearchAdvisoryController::class, 'destroy'])->name('destroy');
                });

                // Committee Assignments (admin CRUD; member-facing routes are
                // registered below outside the manage group)
                Route::prefix('committee-assignments')->name('committee-assignments.')->group(function () {
                    Route::get('/',                                         [CommitteeAssignmentController::class, 'index'])->name('index');
                    Route::get('/compliance',                               [CommitteeAssignmentController::class, 'compliance'])->name('compliance');
                    Route::post('/',                                        [CommitteeAssignmentController::class, 'store'])->name('store');
                    Route::put('/{committeeAssignment}',                    [CommitteeAssignmentController::class, 'update'])->name('update');
                    Route::delete('/{committeeAssignment}',                 [CommitteeAssignmentController::class, 'destroy'])->name('destroy');
                });

                // Supervisory Positions
                Route::prefix('supervisory')->name('supervisory.')->group(function () {
                    Route::get('/',                                       [SupervisoryController::class, 'index'])->name('index');
                    Route::post('/import-divisions',                      [SupervisoryController::class, 'importFromDivisions'])->name('import-divisions');
                    Route::post('/{designation}/assign',                  [SupervisoryController::class, 'assign'])->name('assign');
                    Route::delete('/{designation}/remove',                [SupervisoryController::class, 'remove'])->name('remove');
                });

                // Sections
                Route::prefix('sections')->name('sections.')->group(function () {
                    Route::get('/',                [SectionController::class, 'index'])->name('index');
                    Route::get('/{section}',       [SectionController::class, 'show'])->name('show');
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
                Route::get('/',                    [SubjectController::class, 'index'])->name('index');
                Route::post('/',                   [SubjectController::class, 'store'])->name('store');
                Route::post('/copy-from-year',     [SubjectController::class, 'copyFromYear'])->name('copy-from-year');
                Route::put('/{subject}',           [SubjectController::class, 'update'])->name('update');
                Route::delete('/{subject}',        [SubjectController::class, 'destroy'])->name('destroy');
            });

        // Classrooms
        Route::middleware('permission:faculty_loading.classrooms')
            ->prefix('classrooms')->name('classrooms.')->group(function () {
                Route::get('/',                            [ClassroomController::class, 'index'])->name('index');
                Route::post('/',                           [ClassroomController::class, 'store'])->name('store');
                Route::post('/copy-from-year',             [ClassroomController::class, 'copyFromYear'])->name('copy-from-year');
                Route::put('/{classroom}',                 [ClassroomController::class, 'update'])->name('update');
                Route::delete('/{classroom}',              [ClassroomController::class, 'destroy'])->name('destroy');
                Route::post('/{classroom}/regenerate-nfc', [ClassroomController::class, 'regenerateNfc'])->name('regenerate-nfc');
            });

        // ══════════════════════════════════════════════════════════════════════
        // 6. Setup — academic units, designations
        // ══════════════════════════════════════════════════════════════════════

        // Academic Units
        Route::middleware('permission:faculty_loading.setup')
            ->prefix('academic-units')->name('academic-units.')->group(function () {
                Route::get('/',                      [AcademicUnitController::class, 'index'])->name('index');
                Route::post('/',                     [AcademicUnitController::class, 'store'])->name('store');
                Route::post('/copy-from-year',       [AcademicUnitController::class, 'copyFromYear'])->name('copy-from-year');
                Route::post('/sync-to-offices',      [AcademicUnitController::class, 'syncToOffices'])->name('sync-to-offices');
                Route::put('/{academicUnit}',         [AcademicUnitController::class, 'update'])->name('update');
                Route::delete('/{academicUnit}',      [AcademicUnitController::class, 'destroy'])->name('destroy');
            });

        // Designations (categories + designations) + assign/revoke
        Route::middleware('permission:faculty_loading.setup')
            ->prefix('designations')->name('designations.')->group(function () {
                Route::get('/',                           [DesignationController::class, 'index'])->name('index');

                // Categories
                Route::post('/categories',                [DesignationController::class, 'storeCategory'])->name('categories.store');
                Route::put('/categories/{category}',      [DesignationController::class, 'updateCategory'])->name('categories.update');
                Route::delete('/categories/{category}',   [DesignationController::class, 'destroyCategory'])->name('categories.destroy');

                // Designations
                Route::post('/',                          [DesignationController::class, 'store'])->name('store');
                Route::put('/{designation}',              [DesignationController::class, 'update'])->name('update');
                Route::delete('/{designation}',           [DesignationController::class, 'destroy'])->name('destroy');
            });

        // Designation assign/revoke (requires load_assignments permission, not setup)
        Route::middleware('permission:faculty_loading.load_assignments')
            ->group(function () {
                Route::post('/faculty-loads/{facultyLoad}/designations',        [DesignationController::class, 'assign'])->name('designations.assign');
                Route::delete('/assignments/{assignment}/designation',           [DesignationController::class, 'revoke'])->name('designations.revoke');
            });

        // Designation assign/revoke direct (from Designations catalog page)
        Route::middleware('permission:faculty_loading.manage')
            ->group(function () {
                Route::post('/designations/{designation}/assign-direct',        [DesignationController::class, 'assignDirect'])->name('designations.assign-direct');
                Route::delete('/designations/assignments/{assignment}/revoke',   [DesignationController::class, 'revokeDirect'])->name('designations.revoke-direct');
            });

        // ══════════════════════════════════════════════════════════════════════
        // 7. Vacancies
        // ══════════════════════════════════════════════════════════════════════

        Route::middleware('permission:faculty_loading.vacancies')
            ->prefix('vacancies')->name('vacancies.')->group(function () {
                Route::get('/',                              [FacultyVacancyController::class, 'index'])->name('index');
                Route::post('/',                             [FacultyVacancyController::class, 'store'])->name('store');
                Route::post('/{vacancy}/fill',               [FacultyVacancyController::class, 'fill'])->name('fill');
                Route::post('/{vacancy}/cancel',             [FacultyVacancyController::class, 'cancel'])->name('cancel');
                Route::delete('/{vacancy}',                  [FacultyVacancyController::class, 'destroy'])->name('destroy');
            });

        // ══════════════════════════════════════════════════════════════════════
        // 8. Training Records
        // ══════════════════════════════════════════════════════════════════════

        Route::middleware('permission:faculty_loading.training')
            ->prefix('training-records')->name('training-records.')->group(function () {
                Route::get('/',                              [TrainingRecordController::class, 'index'])->name('index');
                Route::post('/',                             [TrainingRecordController::class, 'store'])->name('store');
                Route::put('/{trainingRecord}',              [TrainingRecordController::class, 'update'])->name('update');
                Route::delete('/{trainingRecord}',           [TrainingRecordController::class, 'destroy'])->name('destroy');

                // Verify/reject requires elevated permission
                Route::middleware('permission:faculty_loading.training.verify')->group(function () {
                    Route::post('/{trainingRecord}/verify',  [TrainingRecordController::class, 'verify'])->name('verify');
                    Route::post('/{trainingRecord}/reject',  [TrainingRecordController::class, 'reject'])->name('reject');
                });
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

                // Workflow transitions
                Route::post('/{schoolYear}/advance', [SchoolYearController::class, 'advance'])->name('advance');
                Route::post('/{schoolYear}/archive', [SchoolYearController::class, 'archive'])->name('archive');
            });
    });
