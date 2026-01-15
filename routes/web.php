<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ITJobRequestController;
use App\Http\Controllers\ICTEquipmentController;

    // Library Borrowings
    Route::get('/library/borrowings', [\App\Http\Controllers\LibraryBorrowingsController::class, 'index'])
        ->name('library.borrowings.index');
    Route::post('/library/borrowings', [\App\Http\Controllers\LibraryBorrowingsController::class, 'store'])
        ->name('library.borrowings.store');
    Route::post('/library/borrowings/{id}/return', [\App\Http\Controllers\LibraryBorrowingsController::class, 'processReturn'])
        ->name('library.borrowings.return');
    Route::post('/library/borrowings/{id}/override', [\App\Http\Controllers\LibraryBorrowingsController::class, 'overrideDueDate'])
        ->name('library.borrowings.override');

    // Library Collections
    Route::get('/library/collections/{id}', [\App\Http\Controllers\LibraryCollectionsController::class, 'show'])->name('library.collections.show');
    Route::get('/library/collections/{id}/history', [\App\Http\Controllers\LibraryBorrowingsController::class, 'collectionHistory'])->name('library.collections.history');
    Route::get('/library/borrowers/{type}/{id}/history', [\App\Http\Controllers\LibraryBorrowingsController::class, 'borrowerHistory'])->name('library.borrowers.history');
use App\Http\Controllers\VehicleRequestController;
use App\Http\Controllers\ICTPMSHistoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolesController;
use App\Models\User;
use App\Http\Controllers\PMSController;
use App\Http\Controllers\AgencyOutcomeController;
use App\Http\Controllers\PerformanceIndicatorController;
use App\Http\Controllers\WorkDistributionPlanController;
use App\Http\Controllers\IPCRController;
use App\Http\Controllers\EmployeeIPCRController;
use App\Http\Controllers\DivisionChiefIPCRController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
// Data Management - Offices
Route::middleware(['auth','role:Administrator'])->group(function(){
    Route::get('/data-management/offices', [App\Http\Controllers\OfficeController::class, 'index'])->name('offices.index');
    Route::post('/data-management/offices', [App\Http\Controllers\OfficeController::class, 'store'])->name('offices.store');
    Route::put('/data-management/offices/{office}', [App\Http\Controllers\OfficeController::class, 'update'])->name('offices.update');
    Route::delete('/data-management/offices/{office}', [App\Http\Controllers\OfficeController::class, 'destroy'])->name('offices.destroy');
});

use Inertia\Inertia;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\LibraryKioskController;

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

// Library kiosk (public, no login required)
Route::get('/library/kiosk', [LibraryKioskController::class, 'index'])->name('library.kiosk');
Route::post('/library/kiosk/scan', [LibraryKioskController::class, 'scan'])->name('library.kiosk.scan');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (PSHS email only)
|--------------------------------------------------------------------------
*/
Route::prefix('it-job-requests')->group(function () {

    // GET — Signed link to approve request
    Route::get('dc/approve/{jobRequest}/{chief}', [ITJobRequestController::class, 'approveByDivisionChiefSigned'])
        ->name('it-job-requests.dc.approve')
        ->middleware('signed');

    // GET — Signed link to show decline form
    Route::get('dc/decline/{jobRequest}/{chief}', [ITJobRequestController::class, 'showDivisionChiefDeclineForm'])
        ->name('it-job-requests.dc.decline')
        ->middleware('signed');

    // POST — submit decline (no signed middleware, CSRF only)
    Route::post('dc/decline/{jobRequest}/{chief}', [ITJobRequestController::class, 'submitDivisionChiefDecline'])
        ->name('it-job-requests.dc.decline.submit');


    // OCD signed routes
    Route::get('ocd/approve/{jobRequest}/{ocd}', [ITJobRequestController::class, 'approveByOCDSigned'])
        ->name('it-job-requests.ocd.approve')
        ->middleware('signed');


    // OCD Approval Decline
    Route::get('it-job-requests/ocd/decline/{jobRequest}/{ocd}', [ITJobRequestController::class, 'showOCDDeclineForm'])
        ->name('it-job-requests.ocd.decline')
        ->middleware('signed');

    Route::post('it-job-requests/ocd/decline/{jobRequest}/{ocd}', [ITJobRequestController::class, 'submitOCDDecline'])
        ->name('it-job-requests.ocd.decline.submit')
        ->middleware('signed');


});

Route::middleware(['auth', 'pshs.email'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        // Compute scholars count: prefer counting rows where a status-like column equals 'Enrolled'
        $scholarsCount = 0;
        try {
            $cols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
            $statusCandidates = ['status','student_status','enrollment_status','enrolled','enrollment','status_desc'];
            $statusField = null;
            foreach ($statusCandidates as $cand) {
                if (in_array($cand, $cols)) { $statusField = $cand; break; }
            }

            if ($statusField) {
                $scholarsCount = DB::table('students')->where($statusField, 'Enrolled')->count();
            } else {
                $scholarsCount = DB::table('students')->count();
            }
        } catch (\Throwable $e) {
            // If table doesn't exist or other DB error, fallback to zero
            logger()->warning('Failed to compute scholars count for dashboard: '.$e->getMessage());
            $scholarsCount = 0;
        }

        // Faculty and Staff counts based on role_id: faculty -> 3, staff -> 4
        try {
            $facultyCount = DB::table('users')->where('role_id', 'like', '%3%')->count();
            $staffCount = DB::table('users')->where('role_id', 'like', '%4%')->count();
        } catch (\Throwable $e) {
            logger()->warning('Failed to compute faculty/staff counts: '.$e->getMessage());
            $facultyCount = 0;
            $staffCount = 0;
        }

        return Inertia::render('Dashboard', [
            'scholarsCount' => $scholarsCount,
            'facultyCount' => $facultyCount,
            'staffCount' => $staffCount,
        ]);
    })->middleware(['verified'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Job Requests
    |--------------------------------------------------------------------------
    */
    Route::get('/job-requests', [ITJobRequestController::class, 'index'])->name('jobrequests.index');
    Route::get('/job-requests/create', [ITJobRequestController::class, 'create'])->name('jobrequests.create');
    Route::post('/job-requests', [ITJobRequestController::class, 'store'])->name('jobrequests.store');
    Route::post('/it-job-requests/{jobRequest}/confirm',[ITJobRequestController::class, 'confirmCompletion']
);

    Route::get('/itjr/{jobRequest}/division-chief/{action}',[ITJobRequestController::class, 'approveByDivisionChief'])->name('itjr.dc.action');
    

    // Vehicle Requests
    Route::get('/vehicle-requests', [VehicleRequestController::class, 'index'])->name('vehicle-requests.index');
    Route::post('/vehicle-requests', [VehicleRequestController::class, 'store'])->name('vehicle-requests.store');
    // Activity Planner
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    // Facility Requests
    Route::get('/facility-requests', [\App\Http\Controllers\FacilityRequestController::class, 'index'])->name('facility-requests.index');
    Route::post('/facility-requests', [\App\Http\Controllers\FacilityRequestController::class, 'store'])->name('facility-requests.store');
    // Service Requests
    Route::get('/service-requests', [\App\Http\Controllers\ServiceRequestController::class, 'index'])->name('service-requests.index');
    Route::post('/service-requests', [\App\Http\Controllers\ServiceRequestController::class, 'store'])->name('service-requests.store');
    Route::put('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'update'])->name('service-requests.update');
    Route::delete('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'destroy'])->name('service-requests.destroy');
    // Driver assignment API
    Route::get('/api/drivers', [\App\Http\Controllers\DriverController::class, 'index'])->name('api.drivers.index');
    Route::post('/vehicle-requests/{vehicleRequest}/assign-driver', [\App\Http\Controllers\DriverController::class, 'assign'])->name('vehicle-requests.assign-driver');
    // Division chief approval via signed link
    // Signed approval link: includes the approver id so the link can be used from email
    Route::get('/vehicle-requests/{vehicleRequest}/approve/{chief}', [VehicleRequestController::class, 'approveByDivisionChief'])
        ->name('vehicle-requests.approve')
        ->middleware(['signed']);

    // OCD approval via signed link (sent to OCD users)
    Route::get('/vehicle-requests/{vehicleRequest}/ocd/approve/{ocd}', [VehicleRequestController::class, 'approveByOCD'])
        ->name('vehicle-requests.ocd.approve')
        ->middleware(['signed']);

    Route::get('/vehicle-requests/{vehicleRequest}/ocd/decline/{ocd}', [VehicleRequestController::class, 'showOcdDeclineForm'])
        ->name('vehicle-requests.ocd.decline')
        ->middleware(['signed']);

    Route::post('/vehicle-requests/{vehicleRequest}/ocd/decline/{ocd}', [VehicleRequestController::class, 'submitOcdDecline'])
        ->name('vehicle-requests.ocd.decline.submit')
        ->middleware(['signed']);

    // Print trip ticket (only GSU Head and Administrator)
    Route::get('/vehicle-requests/{vehicleRequest}/print', [VehicleRequestController::class, 'printTicket'])
        ->name('vehicle-requests.print')
        ->middleware('role:Administrator|GSU Head');

    // Decline flow (signed): show decline form and submit decline
    Route::get('/vehicle-requests/{vehicleRequest}/decline/{chief}', [VehicleRequestController::class, 'showDeclineForm'])
        ->name('vehicle-requests.decline')
        ->middleware(['signed']);

    // Facility Requests: Division chief approve/decline via signed links
    Route::get('/facility-requests/{facilityRequest}/approve/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'approveByDivisionChief'])
        ->name('facility-requests.approve')
        ->middleware(['signed']);

    Route::get('/facility-requests/{facilityRequest}/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'showDeclineForm'])
        ->name('facility-requests.decline')
        ->middleware(['signed']);

    Route::post('/facility-requests/{facilityRequest}/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'submitDecline'])
        ->name('facility-requests.decline.submit')
        ->middleware(['signed']);

    // OCD approval via signed link (sent to OCD users)
    // GSU Head approval via signed link (sent to GSU Head users)
    Route::get('/facility-requests/{facilityRequest}/gsu/approve/{gsu}', [\App\Http\Controllers\FacilityRequestController::class, 'approveByGSU'])
        ->name('facility-requests.gsu.approve')
        ->middleware(['signed']);

    // Service Requests: Division chief approve/decline via signed links
    Route::get('/service-requests/{serviceRequest}/approve/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'approveByDivisionChief'])
        ->name('service-requests.approve')
        ->middleware(['signed']);

    Route::get('/service-requests/{serviceRequest}/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'showDeclineForm'])
        ->name('service-requests.decline')
        ->middleware(['signed']);

    Route::post('/service-requests/{serviceRequest}/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'submitDecline'])
        ->name('service-requests.decline.submit')
        ->middleware(['signed']);

    Route::get('/service-requests/{serviceRequest}/gsu/approve/{gsu}', [\App\Http\Controllers\ServiceRequestController::class, 'approveByGSU'])
        ->name('service-requests.gsu.approve')
        ->middleware(['signed']);

    Route::get('/service-requests/{serviceRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\ServiceRequestController::class, 'showDeclineForm'])
        ->name('service-requests.gsu.decline')
        ->middleware(['signed']);

    Route::post('/service-requests/{serviceRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\ServiceRequestController::class, 'submitDecline'])
        ->name('service-requests.gsu.decline.submit')
        ->middleware(['signed']);

    // Print service request (only GSU Head and Administrator)
    Route::get('/service-requests/{serviceRequest}/print', [\App\Http\Controllers\ServiceRequestController::class, 'printTicket'])
        ->name('service-requests.print')
        ->middleware('role:Administrator|GSU Head');

    Route::get('/facility-requests/{facilityRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\FacilityRequestController::class, 'showGsuDeclineForm'])
        ->name('facility-requests.gsu.decline')
        ->middleware(['signed']);

    Route::post('/facility-requests/{facilityRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\FacilityRequestController::class, 'submitGsuDecline'])
        ->name('facility-requests.gsu.decline.submit')
        ->middleware(['signed']);

    // Messengerial: Division chief approve/decline via signed links (sent by email)
    Route::get('/messengerial/{messengerialRequest}/approve/{chief}', [\App\Http\Controllers\MessengerialController::class, 'approveByDivisionChief'])
        ->name('messengerial.approve')
        ->middleware(['signed']);

    Route::get('/messengerial/{messengerialRequest}/decline/{chief}', [\App\Http\Controllers\MessengerialController::class, 'showDeclineForm'])
        ->name('messengerial.decline')
        ->middleware(['signed']);

    Route::post('/messengerial/{messengerialRequest}/decline/{chief}', [\App\Http\Controllers\MessengerialController::class, 'submitDecline'])
        ->name('messengerial.decline.submit')
        ->middleware(['signed']);

    // Print messengerial request (Administrator, Records, or the requester)
    Route::get('/messengerial/{messengerialRequest}/print', [\App\Http\Controllers\MessengerialController::class, 'printTicket'])
        ->name('messengerial.print');

    // Upload proof of delivery (Records and Administrator)
    Route::post('/messengerial/{messengerialRequest}/upload-proof', [\App\Http\Controllers\MessengerialController::class, 'uploadProof'])
        ->name('messengerial.upload_proof')
        ->middleware('role:Administrator|Records');

    // Messengerial CRUD routes (any authenticated user may create; controller enforces edit/delete rules)
    Route::get('/messengerial', [\App\Http\Controllers\MessengerialController::class, 'index'])->name('messengerial.index');
    Route::post('/messengerial', [\App\Http\Controllers\MessengerialController::class, 'store'])->name('messengerial.store');
    Route::put('/messengerial/{messengerialRequest}', [\App\Http\Controllers\MessengerialController::class, 'update'])->name('messengerial.update');
    Route::delete('/messengerial/{messengerialRequest}', [\App\Http\Controllers\MessengerialController::class, 'destroy'])->name('messengerial.destroy');

    // Health Services - Consultations page
    Route::get('/consultations', [\App\Http\Controllers\ConsultationController::class, 'index'])->name('consultations.index');
    Route::post('/consultations', [\App\Http\Controllers\ConsultationController::class, 'store'])->name('consultations.store');
    Route::put('/consultations/{consultation}', [\App\Http\Controllers\ConsultationController::class, 'update'])->name('consultations.update');

    // Print facility request (only GSU Head and Administrator)
    Route::get('/facility-requests/{facilityRequest}/print', [\App\Http\Controllers\FacilityRequestController::class, 'printTicket'])
        ->name('facility-requests.print')
        ->middleware('role:Administrator|GSU Head');

    Route::post('/vehicle-requests/{vehicleRequest}/decline/{chief}', [VehicleRequestController::class, 'submitDecline'])
        ->name('vehicle-requests.decline.submit')
        ->middleware(['signed']);
    Route::middleware('role:Administrator')->group(function () {
        Route::put('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'update'])->name('vehicle-requests.update');
        Route::delete('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'destroy'])->name('vehicle-requests.destroy');
        // Facility Requests admin actions
        Route::put('/facility-requests/{facilityRequest}', [\App\Http\Controllers\FacilityRequestController::class, 'update'])->name('facility-requests.update');
        Route::delete('/facility-requests/{facilityRequest}', [\App\Http\Controllers\FacilityRequestController::class, 'destroy'])->name('facility-requests.destroy');
    });

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
    
    // Administrator only: Vehicles management
    Route::middleware('role:Administrator')->group(function () {
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        // Facilities admin management
        Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
        Route::post('/facilities', [FacilityController::class, 'store'])->name('facilities.store');
        Route::put('/facilities/{facility}', [FacilityController::class, 'update'])->name('facilities.update');
        Route::delete('/facilities/{facility}', [FacilityController::class, 'destroy'])->name('facilities.destroy');
        
        
    });

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
        Route::post('users/{user}/upload-signature', [UserController::class, 'uploadSignature'])->name('users.upload_signature');
        Route::get('/users-roles', [RolesController::class, 'index'])->name('roles.index');
        Route::post('users-roles', [RolesController::class, 'store'])->name('roles.store');
        Route::put('users-roles/{id}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('users-roles/{id}', [RolesController::class, 'destroy'])->name('roles.destroy');
        Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports.index');
        Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');

        Route::get('/users-division', [RolesController::class, 'showDivisions'])->name('roles.divisions');
        Route::post('users-divisions', [RolesController::class, 'storeDivision'])->name('roles.divisions_store');
        Route::put('users-divisions/{id}', [RolesController::class, 'updateDivision'])->name('roles.division_update');
        Route::post('users-divisions/{division}/upload-signature', [RolesController::class, 'uploadSignature'])->name('roles.divisions.upload_signature');
        
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
    // Students CRUD (Registrar / public admin may use)
    Route::get('/students', [\App\Http\Controllers\StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [\App\Http\Controllers\StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [\App\Http\Controllers\StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{id}/edit', [\App\Http\Controllers\StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{id}', [\App\Http\Controllers\StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{id}', [\App\Http\Controllers\StudentController::class, 'destroy'])->name('students.destroy');
    // Library attendance (authenticated view)
    Route::get('/library/attendance', [\App\Http\Controllers\LibraryAttendanceController::class, 'index'])
        ->name('library.attendance.index')
        ->middleware('role:Administrator|Librarian');

    // Library collections (CRUD for librarians/admins)
    Route::get('/library/collections', [\App\Http\Controllers\LibraryCollectionsController::class, 'index'])
        ->name('library.collections.index')
        ->middleware('role:Administrator|Librarian');
    Route::post('/library/collections', [\App\Http\Controllers\LibraryCollectionsController::class, 'store'])
        ->name('library.collections.store')
        ->middleware('role:Administrator|Librarian');
    Route::put('/library/collections/{id}', [\App\Http\Controllers\LibraryCollectionsController::class, 'update'])
        ->name('library.collections.update')
        ->middleware('role:Administrator|Librarian');
    Route::delete('/library/collections/{id}', [\App\Http\Controllers\LibraryCollectionsController::class, 'destroy'])
        ->name('library.collections.destroy')
        ->middleware('role:Administrator|Librarian');
    // Collection Categories (CRUD for librarians/admins)
    Route::get('/library/collection-categories', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'index'])
        ->name('library.collection-categories.index')
        ->middleware('role:Administrator|Librarian');
    Route::post('/library/collection-categories', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'store'])
        ->name('library.collection-categories.store')
        ->middleware('role:Administrator|Librarian');
    Route::put('/library/collection-categories/{id}', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'update'])
        ->name('library.collection-categories.update')
        ->middleware('role:Administrator|Librarian');
    Route::delete('/library/collection-categories/{id}', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'destroy'])
        ->name('library.collection-categories.destroy')
        ->middleware('role:Administrator|Librarian');
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
// Development-only: send a test email
if (app()->environment('local')) {
    Route::get('/dev/send-test-email', function () {
        \Illuminate\Support\Facades\Mail::raw('Test email from BugsayMIS (dev route)', function ($m) {
            $m->to('mikelfrancisco@gmail.com')->subject('BugsayMIS dev test');
        });
        return response('OK');
    });
}

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
