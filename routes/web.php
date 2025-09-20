<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ITJobRequestController;
use App\Http\Controllers\ICTEquipmentController;
use App\Http\Controllers\ICTPMSHistoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\PMSController;
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
    Route::middleware('role:Administrator')->group(function () {
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
