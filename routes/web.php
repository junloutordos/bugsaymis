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
    Route::get('/library/collections/{id}', [\App\Http\Controllers\LibraryCollectionsController::class, 'show'])->where('id', '[0-9]+')->name('library.collections.show');
    Route::get('/library/collections/{id}/history', [\App\Http\Controllers\LibraryBorrowingsController::class, 'collectionHistory'])->name('library.collections.history');
    Route::get('/library/borrowers/{type}/{id}/history', [\App\Http\Controllers\LibraryBorrowingsController::class, 'borrowerHistory'])->name('library.borrowers.history');
use App\Http\Controllers\VehicleRequestController;
use App\Http\Controllers\WorkRequestController;
use App\Http\Controllers\BuildingController;
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
use App\Http\Controllers\PMTIPCRController;
use App\Http\Controllers\PDSController;
use App\Http\Controllers\PDSTrainingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
// Data Management - Offices
Route::middleware(['auth','role:Administrator'])->group(function(){
    Route::get('/data-management/offices', [App\Http\Controllers\OfficeController::class, 'index'])->name('offices.index');
    Route::post('/data-management/offices', [App\Http\Controllers\OfficeController::class, 'store'])->name('offices.store');
    Route::put('/data-management/offices/{office}', [App\Http\Controllers\OfficeController::class, 'update'])->name('offices.update');
    Route::delete('/data-management/offices/{office}', [App\Http\Controllers\OfficeController::class, 'destroy'])->name('offices.destroy');
    // Buildings
    Route::get('/data-management/buildings', [App\Http\Controllers\BuildingController::class, 'index'])->name('buildings.index');
    Route::post('/data-management/buildings', [App\Http\Controllers\BuildingController::class, 'store'])->name('buildings.store');
    Route::put('/data-management/buildings/{building}', [App\Http\Controllers\BuildingController::class, 'update'])->name('buildings.update');
    Route::delete('/data-management/buildings/{building}', [App\Http\Controllers\BuildingController::class, 'destroy'])->name('buildings.destroy');
    // Rooms
    Route::get('/data-management/rooms', [App\Http\Controllers\RoomController::class, 'index'])->name('rooms.index');
    Route::post('/data-management/rooms', [App\Http\Controllers\RoomController::class, 'store'])->name('rooms.store');
    Route::put('/data-management/rooms/{room}', [App\Http\Controllers\RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/data-management/rooms/{room}', [App\Http\Controllers\RoomController::class, 'destroy'])->name('rooms.destroy');
    // Sections API (used by Rooms UI to load sections for a school year)
    Route::get('/sections', function (\Illuminate\Http\Request $request) {
        $q = \Illuminate\Support\Facades\DB::table('sections')->select('id', 'sectionname as name', 'syid');
        if ($request->has('syid')) {
            $q->where('syid', $request->query('syid'));
        }
        return response()->json($q->get());
    })->name('sections.index');
});

use Inertia\Inertia;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\LibraryKioskController;

/*
|--------------------------------------------------------------------------
                        routeName: "library.statistics.report",
                        href: '#',
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

// Public library collections kiosk (search collections without login)
Route::get('/library/collections/kiosk', [App\Http\Controllers\LibraryCollectionsController::class, 'kiosk'])->name('library.collections.kiosk');
Route::get('/library/collections/kiosk/search', [App\Http\Controllers\LibraryCollectionsController::class, 'publicSearch'])->name('library.collections.kiosk.search');

// Clinic kiosk (public, no login required)
Route::get('/clinic/kiosk', [\App\Http\Controllers\ClinicKioskController::class, 'index'])->name('clinic.kiosk');
Route::post('/clinic/kiosk', [\App\Http\Controllers\ClinicKioskController::class, 'store'])->name('clinic.kiosk.store');

// Guidance kiosk (public, no login required)
Route::get('/guidance/kiosk', [\App\Http\Controllers\GuidanceKioskController::class, 'index'])->name('guidance.kiosk');
Route::post('/guidance/kiosk', [\App\Http\Controllers\GuidanceKioskController::class, 'store'])->name('guidance.kiosk.store');

// DTR Upload (Data Management) - front-end page
Route::get('/data-management/dtr-upload', function () {
    return Inertia::render('DataManagement/DTRUpload');
})->name('data.dtr.upload')->middleware(['auth','role:Administrator']);

// Endpoint to accept uploaded .dat file and insert attendance rows
Route::post('/data-management/dtr-upload', [\App\Http\Controllers\DataManagement\DTRUploadController::class, 'store'])
    ->name('data.dtr.upload.store')
    ->middleware(['auth','role:Administrator']);

    // Consultation log printable report (A4 landscape)
    Route::get('/consultations/log/print', [\App\Http\Controllers\ConsultationController::class, 'logPrint'])
        ->name('consultations.log.print')
        ->middleware('role:Administrator|Nurse|Clinic');
    // Employee consultation log route (uses same controller method)
    Route::get('/consultations/log/print/employee', [\App\Http\Controllers\ConsultationController::class, 'logPrint'])
        ->name('consultations.employee.log.print')
        ->middleware('role:Administrator|Nurse|Clinic');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (PSHS email only)
|--------------------------------------------------------------------------
*/
Route::prefix('it-job-requests')->group(function () {

    // GET — For Approval (Division Chief view)
    Route::get('/for-approval', [ITJobRequestController::class, 'forApproval'])
        ->name('job-requests.for-approval')
        ->middleware(['auth', 'role:DivisionChief']);

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

Route::middleware(['auth'])->group(function () {
    // Redirect user to their PDS (create or edit)
    Route::get('/my-pds', [PDSController::class, 'myPds'])->name('pds.my');

    // Create PDS
    Route::get('/pds/create', [PDSController::class, 'create'])->name('pds.create');
    Route::post('/pds', [PDSController::class, 'store'])->name('pds.store');

    // Edit PDS (view/update own PDS)
    Route::get('/pds/{pds}/edit', [PDSController::class, 'edit'])->name('pds.edit');
    Route::put('/pds/{pds}', [PDSController::class, 'update'])->name('pds.update');

    // Admin-only: list all PDS
    Route::get('/pds', [PDSController::class, 'index'])->middleware('can:admin')->name('pds.index');

    // Optional: show full PDS (for admin or owner)
    Route::get('/pds/{pds}', [PDSController::class, 'show'])->name('pds.show');

    Route::get('/pds/{pds}/export', [PDSController::class, 'exportPDS'])
    ->name('pds.export');

    // Printable HTML version (for browser print)
    Route::get('/pds/{pds}/print', [PDSController::class, 'printPDS'])
        ->name('pds.print');

    // Save overlay coordinates for PDF stamping/overlay alignment
    Route::post('/pds/overlay/save', [PDSController::class, 'saveOverlayCoordinates'])
        ->name('pds.overlay.save');
    
    Route::get('/pds/{pds}/export-pdf', [PdsController::class, 'exportPDSPdf'])
     ->name('pds.export.pdf');

    // Create PDS (only saves user_id)
    Route::post('/pds', [PdsController::class, 'newpds'])
        ->name('pds.newpds');

    Route::post(
        '/pds/{pds}/trainings/upload-csv',
        [PDSTrainingController::class, 'uploadCsv']
    )->name('pds.trainings.upload-csv');

    Route::get('/pds/trainings/template', [PDSTrainingController::class, 'downloadTemplate'])
        ->name('pds.trainings.download-template');


});


Route::middleware(['auth', 'pshs.email'])->group(function () {

    // Dashboard (handled by controller)
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->middleware(['verified'])
        ->name('dashboard');

    // Schedule (Human Resource)
    Route::get('/schedules', [\App\Http\Controllers\HumanResource\ScheduleController::class, 'index'])
        ->name('schedules.index');
    Route::post('/schedules', [\App\Http\Controllers\HumanResource\ScheduleController::class, 'store'])
        ->name('schedules.store');
    Route::put('/schedules/{id}', [\App\Http\Controllers\HumanResource\ScheduleController::class, 'update'])
        ->name('schedules.update');
    Route::delete('/schedules/{id}', [\App\Http\Controllers\HumanResource\ScheduleController::class, 'destroy'])
        ->name('schedules.destroy');
    
    // Date Parameters (Human Resource)
    Route::get('/hr/date-parameters', [\App\Http\Controllers\HumanResource\DateParametersController::class, 'index'])
        ->name('hr.date-parameters.index');
    Route::post('/hr/date-parameters', [\App\Http\Controllers\HumanResource\DateParametersController::class, 'store'])
        ->name('hr.date-parameters.store');
    Route::put('/hr/date-parameters/{id}', [\App\Http\Controllers\HumanResource\DateParametersController::class, 'update'])
        ->name('hr.date-parameters.update');
    Route::delete('/hr/date-parameters/{id}', [\App\Http\Controllers\HumanResource\DateParametersController::class, 'destroy'])
        ->name('hr.date-parameters.destroy');

    // Gate Pass (Human Resource)
    Route::get('/hr/gatepass', [\App\Http\Controllers\HumanResource\GatePassController::class, 'index'])
        ->name('gatepass.index');
    Route::post('/hr/gatepass', [\App\Http\Controllers\HumanResource\GatePassController::class, 'store'])
        ->name('gatepass.store');
    // Division chief approve/decline via signed links (email)
    Route::get('/hr/gatepass/{id}/approve/{chief}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'approveByDivisionChief'])
        ->name('gatepass.approve')
        ->middleware(['signed']);

    Route::get('/hr/gatepass/{id}/decline/{chief}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'showDeclineForm'])
        ->name('gatepass.decline')
        ->middleware(['signed']);

    Route::post('/hr/gatepass/{id}/decline/{chief}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'submitDecline'])
        ->name('gatepass.decline.submit')
        ->middleware(['signed']);

    // OCD signed routes for gatepass (Office of the Campus Director)
    Route::get('/hr/gatepass/{id}/ocd/approve/{ocd}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'approveByOCD'])
        ->name('gatepass.ocd.approve')
        ->middleware(['signed']);

    Route::get('/hr/gatepass/{id}/ocd/decline/{ocd}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'showOcdDeclineForm'])
        ->name('gatepass.ocd.decline')
        ->middleware(['signed']);

    Route::post('/hr/gatepass/{id}/ocd/decline/{ocd}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'submitOcdDecline'])
        ->name('gatepass.ocd.decline.submit')
        ->middleware(['signed']);
    Route::get('/hr/gatepass/{id}/print', [\App\Http\Controllers\HumanResource\GatePassController::class, 'printView'])
        ->name('gatepass.print')
        ->middleware('auth');
    Route::put('/hr/gatepass/{id}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'update'])
        ->name('gatepass.update');
    Route::delete('/hr/gatepass/{id}', [\App\Http\Controllers\HumanResource\GatePassController::class, 'destroy'])
        ->name('gatepass.destroy');
  

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
    Route::post('/vehicle-requests/{vehicleRequest}/approve', [\App\Http\Controllers\VehicleRequestController::class, 'approveInApp'])->name('vehicle-requests.approve.inapp')->middleware('role:DivisionChief');
    Route::post('/vehicle-requests/{vehicleRequest}/decline', [\App\Http\Controllers\VehicleRequestController::class, 'declineInApp'])->name('vehicle-requests.decline.inapp')->middleware('role:DivisionChief');
    // Vehicle bookings API for calendar
    Route::get('/vehicle-bookings', [\App\Http\Controllers\VehicleRequestController::class, 'bookings'])->name('vehicle-requests.bookings');
    // Facility bookings API for calendar
    Route::get('/facility-bookings', [\App\Http\Controllers\FacilityRequestController::class, 'bookings'])->name('facility-requests.bookings');
    // Facility requests for a specific date (used by dashboard date modal)
    Route::get('/facility-requests/by-date', [\App\Http\Controllers\FacilityRequestController::class, 'byDate'])->name('facility-requests.byDate');
    // Activity Planner
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    // Facility Requests
    Route::get('/facility-requests', [\App\Http\Controllers\FacilityRequestController::class, 'index'])->name('facility-requests.index');
    Route::post('/facility-requests', [\App\Http\Controllers\FacilityRequestController::class, 'store'])->name('facility-requests.store');
    Route::post('/facility-requests/{facilityRequest}/approve', [\App\Http\Controllers\FacilityRequestController::class, 'approveInApp'])->name('facility-requests.approve.inapp')->middleware('role:DivisionChief');
    Route::post('/facility-requests/{facilityRequest}/decline', [\App\Http\Controllers\FacilityRequestController::class, 'declineInApp'])->name('facility-requests.decline.inapp')->middleware('role:DivisionChief');
    // Work Requests (General Services)
    Route::get('/work-requests', [WorkRequestController::class, 'index'])->name('work-requests.index')->middleware('role:Administrator|Faculty|Staff|Student|Parent|GSU Head|DivisionChief');
    Route::post('/work-requests', [WorkRequestController::class, 'store'])->name('work-requests.store')->middleware('role:Administrator|Faculty|Staff|Student|Parent|GSU Head|DivisionChief');
    Route::put('/work-requests/{workRequest}', [WorkRequestController::class, 'update'])->name('work-requests.update')->middleware('role:Administrator|Faculty|Staff|Student|Parent|GSU Head|DivisionChief');
    Route::delete('/work-requests/{workRequest}', [WorkRequestController::class, 'destroy'])->name('work-requests.destroy')->middleware('role:Administrator|Faculty|Staff|Student|Parent|GSU Head|DivisionChief');

    // Completion endpoint — only GSU Head or Administrator can mark completed
    Route::post('/work-requests/{workRequest}/complete', [WorkRequestController::class, 'complete'])
        ->name('work-requests.complete')
        ->middleware('role:Administrator|GSU Head');

    // Print view for a single work request (printable slip)

    // Guidance consultations list (Guidance Services)
    Route::get('/guidance/consultations', [\App\Http\Controllers\GuidanceConsultationController::class, 'index'])->name('guidance.consultations.index');
    Route::get('/guidance/students/search', [\App\Http\Controllers\GuidanceConsultationController::class, 'searchStudents'])->name('guidance.students.search')->middleware('role:Administrator|Faculty|Staff|Guidance');
    Route::post('/guidance/referrals', [\App\Http\Controllers\GuidanceConsultationController::class, 'storeReferral'])->name('guidance.referrals.store')->middleware('role:Administrator|Faculty|Staff|Guidance');
    Route::post('/guidance/consultations/{consultation}/assign', [\App\Http\Controllers\GuidanceConsultationController::class, 'assign'])->name('guidance.consultations.assign')->middleware('role:Administrator|Guidance');
    Route::get('/guidance/consultations/{consultation}/admission-slip', [\App\Http\Controllers\GuidanceConsultationController::class, 'admissionSlip'])->name('guidance.consultations.admission-slip')->middleware('role:Administrator|Guidance');
    // Save intervention details (Guidance personnel only)
    Route::get('/guidance/consultations/{consultation}/intervention', [\App\Http\Controllers\GuidanceConsultationController::class, 'getIntervention'])->name('guidance.consultations.intervention.get')->middleware('role:Administrator|Guidance');
    Route::post('/guidance/consultations/{consultation}/intervention', [\App\Http\Controllers\GuidanceConsultationController::class, 'intervention'])->name('guidance.consultations.intervention')->middleware('role:Administrator|Guidance');
    Route::get('/work-requests/{workRequest}/print', [WorkRequestController::class, 'print'])
        ->name('work-requests.print')
        ->middleware('role:Administrator|GSU Head');

    // Division chief approve/decline via signed links for Work Requests
    Route::get('/work-requests/{workRequest}/approve/{chief}', [\App\Http\Controllers\WorkRequestController::class, 'approveByDivisionChief'])
        ->name('work-requests.approve')
        ->middleware(['signed']);

    // Authenticated in-app approve/decline for DivisionChief
    Route::post('/work-requests/{workRequest}/approve', [\App\Http\Controllers\WorkRequestController::class, 'approveInApp'])->name('work-requests.approve.inapp')->middleware('role:DivisionChief');
    Route::post('/work-requests/{workRequest}/decline', [\App\Http\Controllers\WorkRequestController::class, 'declineInApp'])->name('work-requests.decline.inapp')->middleware('role:DivisionChief');

    Route::get('/work-requests/{workRequest}/decline/{chief}', [\App\Http\Controllers\WorkRequestController::class, 'showDeclineForm'])
        ->name('work-requests.decline')
        ->middleware(['signed']);

    Route::post('/work-requests/{workRequest}/decline/{chief}', [\App\Http\Controllers\WorkRequestController::class, 'submitDecline'])
        ->name('work-requests.decline.submit')
        ->middleware(['signed']);
    // FAD approval signed routes
    Route::get('/work-requests/{workRequest}/fad/approve/{chief}', [\App\Http\Controllers\WorkRequestController::class, 'approveByFADChief'])
        ->name('work-requests.fad.approve')
        ->middleware(['signed']);

    // GSU Head approval routes (signed links)
    Route::get('/work-requests/{workRequest}/gsu/approve/{gsu}', [\App\Http\Controllers\WorkRequestController::class, 'approveByGSUHead'])->name('work-requests.gsu.approve');
    Route::get('/work-requests/{workRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\WorkRequestController::class, 'showGSUDeclineForm'])->name('work-requests.gsu.decline');
    Route::post('/work-requests/{workRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\WorkRequestController::class, 'submitGSUDecline'])->name('work-requests.gsu.decline.submit');

    Route::get('/work-requests/{workRequest}/fad/decline/{chief}', [\App\Http\Controllers\WorkRequestController::class, 'showFADDeclineForm'])
        ->name('work-requests.fad.decline')
        ->middleware(['signed']);

    Route::post('/work-requests/{workRequest}/fad/decline/{chief}', [\App\Http\Controllers\WorkRequestController::class, 'submitFADDecline'])
        ->name('work-requests.fad.decline.submit')
        ->middleware(['signed']);
    // Service Requests
    Route::get('/service-requests', [\App\Http\Controllers\ServiceRequestController::class, 'index'])->name('service-requests.index');
    Route::post('/service-requests', [\App\Http\Controllers\ServiceRequestController::class, 'store'])->name('service-requests.store');
    // In-app approval endpoints for Division Chief (named .inapp to avoid collision with signed email routes)
    Route::post('/service-requests/{serviceRequest}/approve', [\App\Http\Controllers\ServiceRequestController::class, 'approveInApp'])->name('service-requests.approve.inapp')->middleware('role:DivisionChief');
    Route::post('/service-requests/{serviceRequest}/decline', [\App\Http\Controllers\ServiceRequestController::class, 'declineInApp'])->name('service-requests.decline.inapp')->middleware('role:DivisionChief');
    Route::put('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'update'])->name('service-requests.update');
    Route::delete('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'destroy'])->name('service-requests.destroy');
    // Assets (General Services)
    Route::get('/assets', [\App\Http\Controllers\AssetController::class, 'index'])->name('assets.index');
    Route::post('/assets', [\App\Http\Controllers\AssetController::class, 'store'])->name('assets.store');
    Route::put('/assets/{asset}', [\App\Http\Controllers\AssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [\App\Http\Controllers\AssetController::class, 'destroy'])->name('assets.destroy');

    // Procurement
    Route::get('/procurements', [\App\Http\Controllers\ProcurementController::class, 'index'])->name('procurements.index');
    Route::post('/procurements', [\App\Http\Controllers\ProcurementController::class, 'store'])->name('procurements.store');
    Route::put('/procurements/{procurement}', [\App\Http\Controllers\ProcurementController::class, 'update'])->name('procurements.update');
    Route::delete('/procurements/{procurement}', [\App\Http\Controllers\ProcurementController::class, 'destroy'])->name('procurements.destroy');
    Route::post('/procurements/{procurement}/items', [\App\Http\Controllers\ProcurementController::class, 'storeItem'])->name('procurements.items.store');
    Route::delete('/procurements/{procurement}/items/{item}', [\App\Http\Controllers\ProcurementController::class, 'destroyItem'])->name('procurements.items.destroy');
    // Send procurement for approval (notifies Budget Officers)
    Route::post('/procurements/{procurement}/send-for-approval', [\App\Http\Controllers\ProcurementController::class, 'sendForApproval'])->name('procurements.sendForApproval');
    // Signed approval/decline links for Budget Officer
    Route::get('/procurements/{procurement}/approve/{approver}', [\App\Http\Controllers\ProcurementController::class, 'approveByBudgetOfficer'])
        ->name('procurements.approve')
        ->middleware(['signed']);
    Route::get('/procurements/{procurement}/decline/{approver}', [\App\Http\Controllers\ProcurementController::class, 'showBudgetOfficerDeclineForm'])
        ->name('procurements.decline')
        ->middleware(['signed']);
    Route::post('/procurements/{procurement}/decline/{approver}', [\App\Http\Controllers\ProcurementController::class, 'submitBudgetOfficerDecline'])
        ->name('procurements.decline.submit')
        ->middleware(['signed']);
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

    // FAD approval signed routes (sent to FAD Chief users after Division Chief approval)
    Route::get('/facility-requests/{facilityRequest}/fad/approve/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'approveByFAD'])
        ->name('facility-requests.fad.approve')
        ->middleware(['signed']);

    Route::get('/facility-requests/{facilityRequest}/fad/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'showFadDeclineForm'])
        ->name('facility-requests.fad.decline')
        ->middleware(['signed']);

    Route::post('/facility-requests/{facilityRequest}/fad/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'submitFadDecline'])
        ->name('facility-requests.fad.decline.submit')
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

    // FAD approval signed routes (sent to FAD Chief users after Division Chief approval)
    Route::get('/service-requests/{serviceRequest}/fad/approve/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'approveByFAD'])
        ->name('service-requests.fad.approve')
        ->middleware(['signed']);

    Route::get('/service-requests/{serviceRequest}/fad/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'showFadDeclineForm'])
        ->name('service-requests.fad.decline')
        ->middleware(['signed']);

    Route::post('/service-requests/{serviceRequest}/fad/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'submitFadDecline'])
        ->name('service-requests.fad.decline.submit')
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

    // Document Tracking
    Route::get('/document-tracking', [\App\Http\Controllers\DocumentTrackingController::class, 'index'])->name('document-tracking.index');
    Route::post('/document-tracking', [\App\Http\Controllers\DocumentTrackingController::class, 'store'])->name('document-tracking.store');
    Route::get('/document-tracking/attachments/{attachment}/download', [\App\Http\Controllers\DocumentTrackingController::class, 'download'])->name('document-tracking.download');
    Route::get('/document-tracking/{document}', [\App\Http\Controllers\DocumentTrackingController::class, 'show'])->name('document-tracking.show');
    Route::post('/document-tracking/routings/{routing}/receive', [\App\Http\Controllers\DocumentTrackingController::class, 'receive'])->name('document-tracking.receive');
    Route::post('/document-tracking/routings/{routing}/action', [\App\Http\Controllers\DocumentTrackingController::class, 'action'])->name('document-tracking.action');
    Route::post('/document-tracking/routings/{routing}/forward', [\App\Http\Controllers\DocumentTrackingController::class, 'forward'])->name('document-tracking.forward');
    Route::post('/document-tracking/routings/{routing}/complete', [\App\Http\Controllers\DocumentTrackingController::class, 'complete'])->name('document-tracking.complete');

    // Messengerial CRUD routes (any authenticated user may create; controller enforces edit/delete rules)
    Route::get('/messengerial', [\App\Http\Controllers\MessengerialController::class, 'index'])->name('messengerial.index');
    Route::post('/messengerial', [\App\Http\Controllers\MessengerialController::class, 'store'])->name('messengerial.store');
    Route::put('/messengerial/{messengerialRequest}', [\App\Http\Controllers\MessengerialController::class, 'update'])->name('messengerial.update');
    Route::delete('/messengerial/{messengerialRequest}', [\App\Http\Controllers\MessengerialController::class, 'destroy'])->name('messengerial.destroy');

    // Health Services - Consultations page
    Route::get('/consultations', [\App\Http\Controllers\ConsultationController::class, 'index'])->name('consultations.index');
    Route::post('/consultations', [\App\Http\Controllers\ConsultationController::class, 'store'])->name('consultations.store');
    Route::put('/consultations/{consultation}', [\App\Http\Controllers\ConsultationController::class, 'update'])->name('consultations.update');
    Route::get('/consultations/{consultation}/print', [\App\Http\Controllers\ConsultationController::class, 'print'])->name('consultations.print');
    Route::delete('/consultations/{consultation}', [\App\Http\Controllers\ConsultationController::class, 'destroy'])->name('consultations.destroy');

    // Health statistics report (date range -> bar chart by reason split by sex)
    Route::get('/health/statistics', [\App\Http\Controllers\HealthStatisticsController::class, 'report'])->name('health.statistics.report');

    // Physician Schedule CRUD (Health > Schedule)
    Route::get('/physician-schedule', [\App\Http\Controllers\PhysicianScheduleController::class, 'index'])->name('physician-schedule.index');
    Route::post('/physician-schedule', [\App\Http\Controllers\PhysicianScheduleController::class, 'store'])->name('physician-schedule.store');
    Route::put('/physician-schedule/{schedule}', [\App\Http\Controllers\PhysicianScheduleController::class, 'update'])->name('physician-schedule.update');
    Route::delete('/physician-schedule/{schedule}', [\App\Http\Controllers\PhysicianScheduleController::class, 'destroy'])->name('physician-schedule.destroy');

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
    Route::put('/ict-equipments/{ictEquipment}', [ICTEquipmentController::class, 'update'])
    ->name('ict-equipments.update');

    // Delete ICT Equipment
    Route::delete('/ict-equipments/{ictEquipment}', [ICTEquipmentController::class, 'destroy'])
        ->name('ict-equipments.destroy');

    Route::get('/ict-equipments/{id}', [ICTEquipmentController::class, 'show'])->name('ict-equipments.show');
    Route::get('/equipment/{ictEquipment}', [ICTEquipmentController::class, 'publicShow'])
    ->name('equipment.public.show');
    Route::post('/ict-equipments/report/generate', [ICTEquipmentController::class, 'generateReport'])->name('ict-equipments.report.generate');

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
    Route::middleware('role:Administrator|HR|DivisionChief|OCD|PMT')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/upload-signature', [UserController::class, 'uploadSignature'])->name('users.upload_signature');
        Route::get('/users/inactive', [UserController::class, 'inactiveIndex'])->name('users.inactive')->middleware('role:Administrator');
        Route::post('/users/{id}/activate', [UserController::class, 'activate'])->name('users.activate')->middleware('role:Administrator');
        Route::get('/users-roles', [RolesController::class, 'index'])->name('roles.index');
        Route::post('users-roles', [RolesController::class, 'store'])->name('roles.store');
        Route::put('users-roles/{id}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('users-roles/{id}', [RolesController::class, 'destroy'])->name('roles.destroy');
        Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports.index');
        Route::get('/reports/audit-logs', [\App\Http\Controllers\Reports\AuditLogController::class, 'index'])->name('reports.audit_logs')->middleware('role:Administrator');
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
        // Disapprove IPCR targets (return for revision)
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/disapprove', [DivisionChiefIPCRController::class, 'disapproveTargets'])
            ->name('division-chief-employee-ipcr.disapprove');
        // Return submitted accomplishment for revision
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/return-accomplishment', [DivisionChiefIPCRController::class, 'returnAccomplishment'])
            ->name('division-chief-employee-ipcr.returnAccomplishment');
        // Save per-plan remark during review
        Route::put('/division-chief-employee-ipcr-plan/{ipcr}/{plan}/remark', [DivisionChiefIPCRController::class, 'savePlanRemark'])
            ->name('division-chief-employee-ipcr-plan.remark');
        // Save supervisor ratings for an IPCR target
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/saveratings', [DivisionChiefIPCRController::class, 'saveRatings'])
            ->name('division-chief-employee-ipcr.saveratings');
        // Save division chief comments/suggestions
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/save-comments', [DivisionChiefIPCRController::class, 'saveComments'])
            ->name('division-chief-employee-ipcr.savecomments');

        // Rate accomplishments for an IPCR
        Route::put('/division-chief-employee-ipcr-plan/{ipcr}/{plan}/rate', [DivisionChiefIPCRController::class, 'rateIPCRPlan'])->name('division-chief-employee-ipcr-plan.rateIPCRPlan');
        // Submit rated IPCR to PMT
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/submit-to-pmt', [DivisionChiefIPCRController::class, 'submitToPMT'])
            ->name('division-chief-employee-ipcr.submitToPMT');
        // Return IPCR to employee after PMT requested revision
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/return-from-pmt', [DivisionChiefIPCRController::class, 'returnFromPMT'])
            ->name('division-chief-employee-ipcr.returnFromPMT');

        // PMT Review Routes
        Route::middleware('role:Administrator|PMT')->group(function () {
            Route::get('/pmt/ipcrs', [PMTIPCRController::class, 'index'])->name('pmt-ipcr.index');
            Route::get('/pmt/ipcrs/{id}', [PMTIPCRController::class, 'show'])->name('pmt-ipcr.show');
            Route::post('/pmt/ipcrs/{employeeIPCR}/approve', [PMTIPCRController::class, 'approve'])->name('pmt-ipcr.approve');
            Route::post('/pmt/ipcrs/{employeeIPCR}/return', [PMTIPCRController::class, 'returnForRevision'])->name('pmt-ipcr.return');
        });

        // Employee plan management (also accessible here for admins)
        Route::delete('/employee-ipcr/{employeeIPCR}/plans/{plan}', [EmployeeIPCRController::class, 'removePlan'])
            ->name('employee-ipcr.removePlan');
        Route::post('/employee-ipcr/{employeeIPCR}/resubmit', [EmployeeIPCRController::class, 'resubmit'])
            ->name('employee-ipcr.resubmit');




        
    });

// Lightweight users JSON endpoint for dropdowns (authenticated)
Route::middleware('auth')->get('/api/users/select', [UserController::class, 'selectList'])->name('users.select');

// HR Employees page (admin-only) — renders the same Users page but labelled as Employees
Route::middleware(['auth','role:Administrator'])->get('/hr/employees', [UserController::class, 'employeesIndex'])->name('hr.employees.index');
Route::middleware(['auth','role:Administrator|HR'])->post('/hr/employees', [UserController::class, 'employeesStore'])->name('hr.employees.store');

    // Human Resource attendance viewer (scoped for Staff/Faculty)
    Route::middleware('auth')->get('/human-resource/attendance', [\App\Http\Controllers\HumanResource\AttendanceController::class, 'index'])->name('hr.attendance.index');

// Library statistics printable report
Route::middleware('auth')->get('/library/statistics/report', [\App\Http\Controllers\LibraryAttendanceController::class, 'report'])->name('library.statistics.report');
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
    Route::middleware('role:Administrator|Staff|Faculty|HR|DivisionChief')->group(function () {

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

        // Remove a plan from an IPCR (when returned for revision)
        Route::delete('/employee-ipcr/{employeeIPCR}/plans/{plan}', [EmployeeIPCRController::class, 'removePlan'])
            ->name('employee-ipcr.removePlan');
        // Resubmit IPCR after revision
        Route::post('/employee-ipcr/{employeeIPCR}/resubmit', [EmployeeIPCRController::class, 'resubmit'])
            ->name('employee-ipcr.resubmit');




        
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
    // Doctor schedules removed — routes deleted

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
