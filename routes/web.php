<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ITJobRequestController;
use App\Http\Controllers\ICTEquipmentController;
use App\Http\Controllers\ICTPMSHistoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\PMSController;
use App\Http\Controllers\AgencyOutcomeController;
use App\Http\Controllers\PerformanceIndicatorController;
use App\Http\Controllers\WorkDistributionPlanController;
use App\Http\Controllers\IPCRController;
use App\Http\Controllers\EmployeeIPCRController;
use App\Http\Controllers\DivisionChiefIPCRController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Google OAuth (Stage 4)
|--------------------------------------------------------------------------
| Google login route that accepts Firebase-authenticated users.
| Email domain is enforced via middleware (pshs.email).
*/
Route::post('/google/login', [GoogleAuthController::class, 'login'])->name('google.login');

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/hello', function () {
    return Inertia::render('Hello', [
        'message' => 'Welcome to BUGSAYMIS!',
    ]);
})->name('hello');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (PSHS email only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'pshs.email'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['verified'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Job Requests
    |--------------------------------------------------------------------------
    */
    Route::get('/job-requests', [ITJobRequestController::class, 'index'])->name('jobrequests.index');
    Route::get('/job-requests/create', [ITJobRequestController::class, 'create'])->name('jobrequests.create');
    Route::post('/job-requests', [ITJobRequestController::class, 'store'])->name('jobrequests.store');

    // Only Admin can assess requests
    Route::post('/job-requests/{jobRequest}/assess', [ITJobRequestController::class, 'assess'])
        ->middleware('role:Administrator')
        ->name('jobrequests.assess');

    Route::put('/job-requests/{itJobRequest}/update', [ITJobRequestController::class, 'update'])
    ->name('job-requests.update');

    Route::get('/ict-equipments', [ICTEquipmentController::class, 'index'])->name('ict-equipments.index');
    Route::post('/ict-equipments', [ICTEquipmentController::class, 'store'])->name('ict-equipments.store');
    Route::get('/ict-equipments/{id}', [ICTEquipmentController::class, 'show'])->name('ict-equipments.show');
    Route::get('/ict-equipment/{ictEquipment}', [ICTEquipmentController::class, 'publicShow'])->name('ict-equipments.public.show');

    Route::get('/ict-pms', [PMSController::class, 'index'])->name('ict-pms.index');
    Route::post('/ict-pms', [PMSController::class, 'store'])->name('ict-pms.store');
    Route::get('/ict-pms/{id}', [PMSController::class, 'show'])->name('ict-pms.show');

    // Assign multiple equipment to PMS
    Route::post('/ict-pms/{pmsId}/assign-equipments', [PMSController::class, 'assignEquipments'])->name('ict-pms.assign-equipments');
    Route::get('/ict-pms/{pms}/equipments', [PMSController::class, 'showEquipments'])->name('ict-pms.show-equipments');

    Route::post('/ict-pms-history', [ICTPMSHistoryController::class, 'store'])->name('ict-pms-history.store');

    /*
    |--------------------------------------------------------------------------
    | Division Chief Approval Routes
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(['auth', 'role:DivisionChief'])->group(function () {
    Route::get('/job-requests/for-approval', [ITJobRequestController::class, 'forApproval'])
        ->name('job-requests.for-approval');

    Route::post('/job-requests/{jobRequest}/division-chief-action', [ITJobRequestController::class, 'approveByDivisionChief'])
        ->name('job-requests.division-chief-action');
    });
    /*
    |--------------------------------------------------------------------------
    | OCD Approval Routes
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(['auth', 'role:OCD'])->group(function () {
    Route::get('/job-requests/ocd-approval', [ITJobRequestController::class, 'ocdApproval'])
        ->name('job-requests.ocd-approval');

    Route::post('/job-requests/{jobRequest}/ocd-action', [ITJobRequestController::class, 'approveByOCD'])
        ->name('job-requests.ocd-action');
    });
    Route::get('/job-requests/{jobRequest}', [ITJobRequestController::class, 'show'])
        ->name('job-requests.show');

    /*
    |--------------------------------------------------------------------------
    | Role-Based Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:Administrator|HR')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/users-roles', [RolesController::class, 'index'])->name('roles.index');
        Route::post('users-roles', [RolesController::class, 'store'])->name('roles.store');
        Route::put('users-roles/{id}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('users-roles/{id}', [RolesController::class, 'destroy'])->name('roles.destroy');
        Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports.index');
        Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');

        Route::get('/users-division', [RolesController::class, 'showDivisions'])->name('roles.divisions');
        Route::post('users-divisions', [RolesController::class, 'storeDivision'])->name('roles.divisions_store');
        Route::put('users-divisions/{id}', [RolesController::class, 'updateDivision'])->name('roles.division_update');
        
        Route::get('/agency-outcomes', [AgencyOutcomeController::class, 'index'])->name('outcome.index');
        Route::post('agency-outcomes', [AgencyOutcomeController::class, 'store'])->name('outcome.store');
        Route::put('agency-outcomes/{id}', [AgencyOutcomeController::class, 'update'])->name('outcome.update');
        Route::delete('agency-outcomes/{id}', [AgencyOutcomeController::class, 'destroy'])->name('outcome.destroy');
        
        Route::get('/performance-indicators', [PerformanceIndicatorController::class, 'index'])->name('performanceindicator.index');
        Route::post('performance-indicators', [PerformanceIndicatorController::class, 'store'])->name('performanceindicator.store');
        Route::put('performance-indicators/{id}', [PerformanceIndicatorController::class, 'update'])->name('performanceindicator.update');
        Route::delete('performance-indicators/{id}', [PerformanceIndicatorController::class, 'destroy'])->name('performanceindicator.destroy');

        Route::get('/work-distributions', [WorkDistributionPlanController::class, 'index'])->name('workdistribution.index');
        Route::post('work-distributions', [WorkDistributionPlanController::class, 'store'])->name('workdistribution.store');
        Route::put('work-distributions/{id}', [WorkDistributionPlanController::class, 'update'])->name('workdistribution.update');
        Route::delete('work-distributions/{id}', [WorkDistributionPlanController::class, 'destroy'])->name('workdistribution.destroy');

        //New IPCR Routes
        Route::get('/employee-ipcr', [EmployeeIPCRController::class, 'index'])->name('employee-ipcr.index');
        Route::post('/employee-ipcr', [EmployeeIPCRController::class, 'store'])->name('employee-ipcr.store');
        Route::put('/employee-ipcr/{employeeIPCR}', [EmployeeIPCRController::class, 'update'])->name('employee-ipcr.update');
        Route::delete('/employee-ipcr/{employeeIPCR}', [EmployeeIPCRController::class, 'destroy'])->name('employee-ipcr.destroy');
        Route::post('/employee-ipcr/{employeeIPCR}/plans', [EmployeeIPCRController::class, 'addPlans'])->name('employee-ipcr.addPlans');
        Route::get('/employee-ipcr/{id}', [EmployeeIPCRController::class, 'show'])->name('employee-ipcr.show');
        Route::put('employee-ipcr-plan/{ipcr}/{plan}',[EmployeeIPCRController::class, 'updateSelfRating'])->name('employee-ipcr-plan.updateSelfRating');
        // Employee IPCR Workflow Actions
        // Submit IPCR for review
        Route::post('/employee-ipcr/{employeeIPCR}/submit-review', [EmployeeIPCRController::class, 'submitForReview'])
            ->name('employee-ipcr.submitReview');

        // Submit IPCR for rating
        Route::post('/employee-ipcr/{employeeIPCR}/submit-rating', [EmployeeIPCRController::class, 'submitForRating'])
            ->name('employee-ipcr.submitRating');

        // Supervisor reviews and returns IPCR
        // View all subordinates IPCRs
        Route::get('/division-chief/ipcrs', [DivisionChiefIPCRController::class, 'index'])
            ->name('division-chief-ipcr.index');

        // View single IPCR with comments and plans
        
        Route::get('/division-chief-employee-ipcr/{id}', [DivisionChiefIPCRController::class, 'show'])->name('division-employee-ipcr.show');
        // Approve an IPCR target
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/targetsapproval', [DivisionChiefIPCRController::class, 'approveTargets'])
            ->name('division-chief-employee-ipcr.targetsapproval');
        // Save supervisor ratings for an IPCR target
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/saveratings', [DivisionChiefIPCRController::class, 'saveRatings'])
            ->name('division-chief-employee-ipcr.saveratings');
        // Save division chief comments/suggestions
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/save-comments', [DivisionChiefIPCRController::class, 'saveComments'])
            ->name('division-chief-employee-ipcr.savecomments');
        
        // Rate accomplishments for an IPCR
        Route::put('/division-chief-employee-ipcr-plan/{ipcr}/{plan}/rate', [DivisionChiefIPCRController::class, 'rateIPCRPlan'])->name('division-chief-employee-ipcr-plan.rateIPCRPlan');




        
    });
    Route::middleware('role:Administrator|Staff|Faculty|HR')->group(function () {
        
        //New IPCR Routes
        Route::get('/employee-ipcr', [EmployeeIPCRController::class, 'index'])->name('employee-ipcr.index');
        Route::post('/employee-ipcr', [EmployeeIPCRController::class, 'store'])->name('employee-ipcr.store');
        Route::put('/employee-ipcr/{employeeIPCR}', [EmployeeIPCRController::class, 'update'])->name('employee-ipcr.update');
        Route::delete('/employee-ipcr/{employeeIPCR}', [EmployeeIPCRController::class, 'destroy'])->name('employee-ipcr.destroy');
        Route::post('/employee-ipcr/{employeeIPCR}/plans', [EmployeeIPCRController::class, 'addPlans'])->name('employee-ipcr.addPlans');
        Route::get('/employee-ipcr/{id}', [EmployeeIPCRController::class, 'show'])->name('employee-ipcr.show');
        Route::put('employee-ipcr-plan/{ipcr}/{plan}',[EmployeeIPCRController::class, 'updateSelfRating'])->name('employee-ipcr-plan.updateSelfRating');
        // Employee IPCR Workflow Actions
        // Submit IPCR for review
        Route::post('/employee-ipcr/{employeeIPCR}/submit-review', [EmployeeIPCRController::class, 'submitForReview'])
            ->name('employee-ipcr.submitReview');

        // Submit IPCR for rating
        Route::post('/employee-ipcr/{employeeIPCR}/submit-rating', [EmployeeIPCRController::class, 'submitForRating'])
            ->name('employee-ipcr.submitRating');

        



        
    });

    Route::middleware('role:Faculty')->group(function () {
        Route::get('/faculty/reports', fn () => Inertia::render('Faculty/Reports'))->name('faculty.reports');
    });

    Route::middleware('role:Staff')->group(function () {
        Route::get('/staff/tasks', fn () => Inertia::render('Staff/Tasks'))->name('staff.tasks');
    });

    Route::middleware('role:Student')->group(function () {
        Route::get('/student/courses', fn () => Inertia::render('Student/Courses'))->name('student.courses');
    });

    Route::middleware('role:Parent')->group(function () {
        Route::get('/parent/overview', fn () => Inertia::render('Parent/Overview'))->name('parent.overview');
    });

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
