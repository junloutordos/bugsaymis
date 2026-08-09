<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ITJobRequestController;
use App\Http\Controllers\ICTEquipmentController;
use App\Http\Controllers\ComputerLabController;
use App\Http\Controllers\ComputerLabScheduleApprovalController;
use App\Http\Controllers\HR\EmployeeDocumentController;
use App\Http\Controllers\ApprovalInboxController;
use App\Http\Controllers\AtlasGoDownloadController;
use App\Http\Controllers\DynaDownloadController;

// ECS container health check — no auth, no session
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');


// Developer portfolio — public, no auth
Route::get('/developer', fn () => inertia('Developer'))->name('developer');

// AtlasGo mobile app — public direct-download (sideload) while Play Store listing is pending
Route::get('/downloads/atlasgo', [AtlasGoDownloadController::class, 'download'])->name('atlasgo.download');

// Dyna macOS app — Sparkle auto-update feed + signed release DMG download, both public/no-auth
Route::get('/downloads/dyna/appcast.xml', [DynaDownloadController::class, 'appcast'])->name('dyna.appcast');
Route::get('/downloads/dyna/download/{filename}', [DynaDownloadController::class, 'download'])->name('dyna.download');

// Data Privacy Policy — public, no auth (required by app store listings)
Route::get('/privacy-policy', fn () => inertia('Privacy/Public'))->name('privacy.public');

// Account & data deletion instructions — public, no auth (required by Play Store account-deletion policy)
Route::get('/account-deletion', fn () => inertia('AccountDeletion/Public'))->name('account-deletion.public');

Route::get('/verify/hr-document/{token}', [\App\Http\Controllers\HR\HrDocumentVerificationController::class, 'show'])
    ->name('hr.document-requests.verify');

// Digital calling card — public, no auth
Route::get('/card/junlou', fn () => inertia('PublicCard'))->name('public.card');

// ── GAD Data Dashboard — public, no auth ─────────────────────────────────────
Route::get('/gad-data', [\App\Http\Controllers\GadDataController::class, 'index'])
    ->name('gad-data');

// ── Self-service Enrollment Application — public, no auth ────────────────────
Route::get('/enroll', [\App\Http\Controllers\Registrar\EnrollmentApplicationController::class, 'create'])
    ->name('enrollment-application.create');
Route::post('/enroll', [\App\Http\Controllers\Registrar\EnrollmentApplicationController::class, 'store'])
    ->name('enrollment-application.store');
Route::get('/enroll/success/{referenceNo}', [\App\Http\Controllers\Registrar\EnrollmentApplicationController::class, 'success'])
    ->name('enrollment-application.success')
    ->where('referenceNo', 'ENR-[0-9]{4}-[0-9]+');

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
use App\Http\Controllers\EmployeeIPCRController;
use App\Http\Controllers\DivisionChiefIPCRController;
use App\Http\Controllers\HRIPCRController;
use App\Http\Controllers\PMTIPCRController;
use App\Http\Controllers\PDSController;
use App\Http\Controllers\PDSTrainingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// ── System health check (auth required — internal monitoring only) ────────────
Route::get('/_status', [\App\Http\Controllers\HealthController::class, 'check'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('system.health');

// Data Management - Offices
Route::middleware(['auth','permission:roles.assign'])->group(function(){
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
    // Committees
    Route::get('/data-management/committees', [App\Http\Controllers\CommitteeController::class, 'index'])->name('committees.index');
    Route::post('/data-management/committees', [App\Http\Controllers\CommitteeController::class, 'store'])->name('committees.store');
    Route::put('/data-management/committees/{committee}', [App\Http\Controllers\CommitteeController::class, 'update'])->name('committees.update');
    Route::delete('/data-management/committees/{committee}', [App\Http\Controllers\CommitteeController::class, 'destroy'])->name('committees.destroy');
    Route::post('/data-management/committees/{committee}/sync-to-term', [App\Http\Controllers\CommitteeController::class, 'syncToTerm'])->name('committees.sync-to-term');
    // Special Assignments
    Route::get('/data-management/special-assignments', [App\Http\Controllers\SpecialAssignmentController::class, 'index'])->name('special-assignments.index');
    Route::post('/data-management/special-assignments', [App\Http\Controllers\SpecialAssignmentController::class, 'store'])->name('special-assignments.store');
    Route::put('/data-management/special-assignments/{specialAssignment}', [App\Http\Controllers\SpecialAssignmentController::class, 'update'])->name('special-assignments.update');
    Route::delete('/data-management/special-assignments/{specialAssignment}', [App\Http\Controllers\SpecialAssignmentController::class, 'destroy'])->name('special-assignments.destroy');
    // Campuses
    Route::get('/data-management/campuses', [App\Http\Controllers\DataManagement\CampusController::class, 'index'])->name('campuses.index');
    Route::post('/data-management/campuses', [App\Http\Controllers\DataManagement\CampusController::class, 'store'])->name('campuses.store');
    Route::put('/data-management/campuses/{campus}', [App\Http\Controllers\DataManagement\CampusController::class, 'update'])->name('campuses.update');
    Route::delete('/data-management/campuses/{campus}', [App\Http\Controllers\DataManagement\CampusController::class, 'destroy'])->name('campuses.destroy');
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

// Socialite OAuth (server-side, no popup)
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

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
        'message' => 'Welcome to Atlas!',
    ]);
})->name('hello');

// Library kiosk (public, no login required)
Route::get('/library/kiosk', [LibraryKioskController::class, 'index'])->name('library.kiosk');
Route::post('/library/kiosk/scan', [LibraryKioskController::class, 'scan'])
    ->middleware('throttle:30,1')
    ->name('library.kiosk.scan');

// Public library collections kiosk (search collections without login)
Route::get('/library/collections/kiosk', [App\Http\Controllers\LibraryCollectionsController::class, 'kiosk'])->name('library.collections.kiosk');
Route::get('/library/collections/kiosk/search', [App\Http\Controllers\LibraryCollectionsController::class, 'publicSearch'])
    ->middleware('throttle:60,1')
    ->name('library.collections.kiosk.search');

// Clinic kiosk (public, no login required)
Route::get('/clinic/kiosk', [\App\Http\Controllers\ClinicKioskController::class, 'index'])->name('clinic.kiosk');
Route::post('/clinic/kiosk', [\App\Http\Controllers\ClinicKioskController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('clinic.kiosk.store');

// Guidance kiosk (public, no login required)
Route::get('/guidance/kiosk', [\App\Http\Controllers\GuidanceKioskController::class, 'index'])->name('guidance.kiosk');
Route::post('/guidance/kiosk', [\App\Http\Controllers\GuidanceKioskController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('guidance.kiosk.store');


    // Consultation log printable report (A4 landscape)
    Route::get('/consultations/log/print', [\App\Http\Controllers\ConsultationController::class, 'logPrint'])
        ->name('consultations.log.print')
        ->middleware('permission:health.view');
    // Employee consultation log route (uses same controller method)
    Route::get('/consultations/log/print/employee', [\App\Http\Controllers\ConsultationController::class, 'logPrint'])
        ->name('consultations.employee.log.print')
        ->middleware('permission:health.view');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (PSHS email only)
|--------------------------------------------------------------------------
*/
Route::prefix('it-job-requests')->group(function () {

    // GET — For Approval (Division Chief view)
    Route::get('/for-approval', [ITJobRequestController::class, 'forApproval'])
        ->name('job-requests.for-approval')
        ->middleware(['auth', 'permission:it.requests.manage']);

    // GET — Signed link to approve request
    Route::get('dc/approve/{jobRequest}/{chief}', [ITJobRequestController::class, 'approveByDivisionChiefSigned'])
        ->name('it-job-requests.dc.approve')
        ->middleware('signed');

    // GET — Signed link to show decline form
    Route::get('dc/decline/{jobRequest}/{chief}', [ITJobRequestController::class, 'showDivisionChiefDeclineForm'])
        ->name('it-job-requests.dc.decline')
        ->middleware('signed');

    Route::post('dc/decline/{jobRequest}/{chief}', [ITJobRequestController::class, 'submitDivisionChiefDecline'])
        ->name('it-job-requests.dc.decline.submit')
        ->middleware('signed');


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

// ── Web Push subscriptions ────────────────────────────────────────────────────
Route::get('/api/push-subscriptions/vapid-public-key', [\App\Http\Controllers\PushSubscriptionController::class, 'vapidPublicKey'])->name('push.vapid-key');
Route::middleware(['auth'])->group(function () {
    Route::post('/api/push-subscriptions',   [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/api/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

// ── In-app notifications ──────────────────────────────────────────────────────
Route::middleware(['auth'])->prefix('api/notifications')->group(function () {
    Route::get('/',           [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/read-all',  [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

// Storage proxy — serves private S3 files through the app (auth required)
Route::middleware(['auth'])->get('/media/{path}', [\App\Http\Controllers\StorageProxyController::class, 'serve'])
    ->where('path', '.+')
    ->name('storage.proxy');

Route::middleware(['auth'])->group(function () {
    // Redirect user to their PDS (create or edit)
    Route::get('/my-pds', [PDSController::class, 'myPds'])->name('pds.my');

    // Create PDS
    Route::get('/pds/create', [PDSController::class, 'create'])->name('pds.create');
    Route::post('/pds', [PDSController::class, 'store'])->name('pds.store');

    // Edit PDS (view/update own PDS)
    Route::get('/pds/{pds}/edit', [PDSController::class, 'edit'])->name('pds.edit');
    Route::put('/pds/{pds}', [PDSController::class, 'update'])->name('pds.update');

    // HR/Admin: list all employee PDS statuses
    Route::get('/pds', [PDSController::class, 'index'])->middleware('permission:pds.view_all')->name('pds.index');

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

    Route::get('/pds/{pds}/wes/pdf', [PDSController::class, 'exportWesPdf'])
        ->name('pds.wes.pdf');

    Route::post('/pds/{pds}/passport-photo', [PDSController::class, 'updatePassportPhoto'])->name('pds.passport-photo');

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

    // Executive Dashboard — cross-system analytics for top management
    Route::middleware('permission:executive.dashboard.view')->group(function () {
        Route::get('/executive-dashboard', [\App\Http\Controllers\ExecutiveDashboardController::class, 'index'])
            ->name('executive.dashboard');
        Route::post('/executive-dashboard/refresh', [\App\Http\Controllers\ExecutiveDashboardController::class, 'refresh'])
            ->name('executive.dashboard.refresh');
    });

    Route::get('/general-services/dashboard', [\App\Http\Controllers\GeneralServicesDashboardController::class, 'index'])
        ->name('general-services.dashboard');
    Route::get('/general-services/dashboard/events', [\App\Http\Controllers\GeneralServicesDashboardController::class, 'events'])
        ->name('general-services.dashboard.events');

    // ── Lost & Found (GSU custody) ────────────────────────────────────────────
    // Any authenticated employee can view the board and file reports; custody
    // actions (receive/store/match/release/dispose) require lostfound.manage.
    Route::prefix('lost-found')->name('lost-found.')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\LostFoundController::class, 'index'])->name('index');
        Route::post('/',                   [\App\Http\Controllers\LostFoundController::class, 'store'])->name('store');
        Route::post('/{item}/close',       [\App\Http\Controllers\LostFoundController::class, 'close'])->name('close')->whereNumber('item');
        Route::get('/photo/{item}',        [\App\Http\Controllers\LostFoundController::class, 'photo'])->name('photo')->whereNumber('item');

        Route::middleware('permission:lostfound.manage')->group(function () {
            Route::post('/walk-in',          [\App\Http\Controllers\LostFoundController::class, 'walkIn'])->name('walk-in');
            Route::post('/{item}/receive',   [\App\Http\Controllers\LostFoundController::class, 'receive'])->name('receive')->whereNumber('item');
            Route::post('/{item}/store-at',  [\App\Http\Controllers\LostFoundController::class, 'storeAt'])->name('store-at')->whereNumber('item');
            Route::post('/{item}/match',     [\App\Http\Controllers\LostFoundController::class, 'match'])->name('match')->whereNumber('item');
            Route::post('/{item}/release',   [\App\Http\Controllers\LostFoundController::class, 'release'])->name('release')->whereNumber('item');
            Route::post('/{item}/dispose',   [\App\Http\Controllers\LostFoundController::class, 'dispose'])->name('dispose')->whereNumber('item');
        });
    });

    // ── Help Documentation ────────────────────────────────────────────────────
    Route::get('/docs', fn () => inertia('Docs/Index'))->name('docs.index');

    // ── Issuances ─────────────────────────────────────────────────────────────
    // No group-level permission gate: any tagged recipient must be able to
    // reach these routes, and every method below already enforces its own
    // correct admin-or-recipient check (see IssuanceController).
    Route::prefix('issuances')->name('issuances.')->group(function () {
        Route::get('/',                       [\App\Http\Controllers\IssuanceController::class, 'index'])->name('index');
        Route::get('/create',                 [\App\Http\Controllers\IssuanceController::class, 'create'])->name('create')->middleware('permission:issuances.manage');
        Route::post('/',                      [\App\Http\Controllers\IssuanceController::class, 'store'])->name('store')->middleware('permission:issuances.manage');
        Route::get('/{issuance}/supplements/create', [\App\Http\Controllers\IssuanceController::class, 'createSupplement'])->name('supplements.create')->middleware('permission:issuances.manage');
        Route::post('/{issuance}/supplements', [\App\Http\Controllers\IssuanceController::class, 'storeSupplement'])->name('supplements.store')->middleware('permission:issuances.manage');

        // Settings (manage only) — must be defined before /{issuance} to avoid route clash
        Route::middleware('permission:issuances.manage')->group(function () {
            Route::get('/settings',              [\App\Http\Controllers\IssuanceSettingsController::class, 'index'])->name('settings.index');
            Route::post('/settings/types',       [\App\Http\Controllers\IssuanceSettingsController::class, 'storeType'])->name('settings.types.store');
            Route::put('/settings/types/{code}', [\App\Http\Controllers\IssuanceSettingsController::class, 'updateType'])->name('settings.types.update');
            Route::delete('/settings/types/{code}', [\App\Http\Controllers\IssuanceSettingsController::class, 'destroyType'])->name('settings.types.destroy');
            Route::put('/settings/series',       [\App\Http\Controllers\IssuanceSettingsController::class, 'upsertSeries'])->name('settings.series.upsert');
        });

        Route::get('/{issuance}',             [\App\Http\Controllers\IssuanceController::class, 'show'])->name('show');
        Route::post('/{issuance}/release',    [\App\Http\Controllers\IssuanceController::class, 'release'])->name('release')->middleware('permission:issuances.manage');
        Route::post('/{issuance}/archive',    [\App\Http\Controllers\IssuanceController::class, 'archive'])->name('archive')->middleware('permission:issuances.manage');
        Route::post('/{issuance}/unarchive',  [\App\Http\Controllers\IssuanceController::class, 'unarchive'])->name('unarchive')->middleware('permission:issuances.manage');
        Route::delete('/{issuance}',          [\App\Http\Controllers\IssuanceController::class, 'destroy'])->name('destroy')->middleware('permission:issuances.manage');
        Route::post('/{issuance}/acknowledge',[\App\Http\Controllers\IssuanceController::class, 'acknowledge'])->name('acknowledge');
        Route::get('/{issuance}/pdf',         [\App\Http\Controllers\IssuanceController::class, 'downloadPdf'])->name('pdf');
        Route::get('/{issuance}/scan',        [\App\Http\Controllers\IssuanceController::class, 'viewScan'])->name('scan');
        Route::post('/{issuance}/recipients/{recipient}/resend', [\App\Http\Controllers\IssuanceController::class, 'resendRecipientEmail'])
            ->name('recipients.resend')->middleware('permission:issuances.manage');
        Route::post('/{issuance}/recipients/resend-bulk', [\App\Http\Controllers\IssuanceController::class, 'resendBulkEmails'])
            ->name('recipients.resendBulk')->middleware('permission:issuances.manage');
    });

    // ── Knowledge Management — OED Issuances ─────────────────────────────────
    Route::prefix('knowledge-management')->name('km.')->middleware('permission:km.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\KnowledgeManagementController::class, 'index'])->name('index');

        Route::middleware('permission:km.manage')->group(function () {
            Route::get('/create',                           [\App\Http\Controllers\KnowledgeManagementController::class, 'create'])->name('create');
            Route::post('/',                                [\App\Http\Controllers\KnowledgeManagementController::class, 'store'])->name('store');
            Route::get('/{oedIssuance}/edit',               [\App\Http\Controllers\KnowledgeManagementController::class, 'edit'])->name('edit');
            Route::put('/{oedIssuance}',                    [\App\Http\Controllers\KnowledgeManagementController::class, 'update'])->name('update');
            Route::delete('/{oedIssuance}',                 [\App\Http\Controllers\KnowledgeManagementController::class, 'destroy'])->name('destroy');
            Route::post('/categories',                      [\App\Http\Controllers\KnowledgeManagementController::class, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{code}',                [\App\Http\Controllers\KnowledgeManagementController::class, 'updateCategory'])->name('categories.update');
        });

        Route::get('/{oedIssuance}',              [\App\Http\Controllers\KnowledgeManagementController::class, 'show'])->name('show');
        Route::post('/{oedIssuance}/acknowledge', [\App\Http\Controllers\KnowledgeManagementController::class, 'acknowledge'])->name('acknowledge');
        Route::get('/{oedIssuance}/file',         [\App\Http\Controllers\KnowledgeManagementController::class, 'viewFile'])->name('file');
        Route::get('/{oedIssuance}/download',     [\App\Http\Controllers\KnowledgeManagementController::class, 'download'])->name('download');
    });

    // ── Travel ───────────────────────────────────────────────────────────────
    Route::prefix('travel')->name('travel.')->middleware('permission:travel.view')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\TravelController::class, 'dashboard'])->name('dashboard');
        Route::get('/', [\App\Http\Controllers\TravelController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\TravelController::class, 'store'])->name('store')->middleware('permission:travel.create');
        Route::get('/{travel}', [\App\Http\Controllers\TravelController::class, 'show'])->name('show');
        Route::put('/{travel}', [\App\Http\Controllers\TravelController::class, 'update'])->name('update');
        Route::post('/{travel}/submit', [\App\Http\Controllers\TravelController::class, 'submit'])->name('submit');
        Route::post('/{travel}/division-approve', [\App\Http\Controllers\TravelController::class, 'divisionApprove'])->name('division-approve')->middleware('permission:travel.approve.division');
        Route::post('/{travel}/fad-review', [\App\Http\Controllers\TravelController::class, 'fadReview'])->name('fad-review')->middleware('permission:travel.review.fad');
        Route::post('/{travel}/ocd-approve', [\App\Http\Controllers\TravelController::class, 'ocdApprove'])->name('ocd-approve')->middleware('permission:travel.approve.ocd');
        Route::post('/{travel}/return', [\App\Http\Controllers\TravelController::class, 'returnForRevision'])->name('return');
        Route::post('/{travel}/complete', [\App\Http\Controllers\TravelController::class, 'complete'])->name('complete')->middleware('permission:travel.review.fad|travel.finance');
        Route::post('/{travel}/liquidate', [\App\Http\Controllers\TravelController::class, 'liquidate'])->name('liquidate')->middleware('permission:travel.finance');
        Route::post('/{travel}/itinerary', [\App\Http\Controllers\TravelController::class, 'saveItinerary'])->name('itinerary.save');
        Route::post('/{travel}/links', [\App\Http\Controllers\TravelController::class, 'saveLinks'])->name('links.save');
        Route::post('/{travel}/flight-authority', [\App\Http\Controllers\TravelController::class, 'saveFlightAuthority'])->name('flight-authority.save');
        Route::post('/{travel}/flight-authority/approve', [\App\Http\Controllers\TravelController::class, 'approveFlightAuthority'])->name('flight-authority.approve')->middleware('permission:travel.review.fad');
        Route::post('/{travel}/attachments', [\App\Http\Controllers\TravelController::class, 'uploadAttachment'])->name('attachments.store');
        Route::get('/{travel}/attachments/{attachment}', [\App\Http\Controllers\TravelController::class, 'downloadAttachment'])->name('attachments.download');
        Route::delete('/{travel}/attachments/{attachment}', [\App\Http\Controllers\TravelController::class, 'deleteAttachment'])->name('attachments.destroy');
        Route::get('/{travel}/iot/print', [\App\Http\Controllers\TravelController::class, 'printIot'])->name('iot.print');
    });


    // ── Data Privacy Policy ───────────────────────────────────────────────────
    Route::get('/privacy', fn () => inertia('Privacy/Index'))->name('privacy.index');

    // ── Unified Approvals Inbox ───────────────────────────────────────────────
    Route::get('/inbox', [ApprovalInboxController::class, 'index'])->name('approvals.inbox');
    Route::post('/inbox/{type}/{id}/approve', [ApprovalInboxController::class, 'approve'])->name('approvals.approve');
    Route::post('/inbox/{type}/{id}/decline', [ApprovalInboxController::class, 'decline'])->name('approvals.decline');

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
    Route::post('/csm', [\App\Http\Controllers\CsmResponseController::class, 'store'])->name('csm.store');

    // ── CSM Feedback Center (admin/MIS) ───────────────────────────────────────
    Route::get('/csm/dashboard', [\App\Http\Controllers\CSMFeedbackController::class, 'dashboard'])->name('csm.dashboard');
    Route::get('/csm/list',      [\App\Http\Controllers\CSMFeedbackController::class, 'index'])->name('csm.list');
    Route::get('/csm/list/{csmResponse}', [\App\Http\Controllers\CSMFeedbackController::class, 'show'])->name('csm.show');
    Route::get('/csm/export',    [\App\Http\Controllers\CSMFeedbackController::class, 'export'])->name('csm.export');
    Route::get('/mis/dashboard', [\App\Http\Controllers\MISDashboardController::class, 'index'])
        ->middleware('permission:it.requests.manage')
        ->name('mis.dashboard');
    Route::get('/job-requests/check-pending-itjr', [ITJobRequestController::class, 'checkPendingActedByMis'])->name('jobrequests.check-pending');
    Route::get('/job-requests/export-pdf', [ITJobRequestController::class, 'exportPdf'])->name('jobrequests.export-pdf');
    Route::get('/job-requests/queue', [ITJobRequestController::class, 'queue'])->name('jobrequests.queue');
    Route::put('/job-requests/{jobRequest}/priority', [ITJobRequestController::class, 'updatePriority'])->name('jobrequests.update-priority');
    Route::get('/job-requests', [ITJobRequestController::class, 'index'])->name('jobrequests.index');
    Route::get('/job-requests/create', [ITJobRequestController::class, 'create'])->name('jobrequests.create');
    Route::post('/job-requests', [ITJobRequestController::class, 'store'])->name('jobrequests.store');
    Route::delete('/job-requests/{jobRequest}', [ITJobRequestController::class, 'destroy'])->name('jobrequests.destroy');
    Route::get('/job-requests/{jobRequest}/print', [ITJobRequestController::class, 'printForm'])->name('jobrequests.print');
    Route::patch('/app-versions/{appVersion}', [\App\Http\Controllers\AppVersionController::class, 'update'])->name('app-versions.update');
    Route::post('/it-job-requests/{jobRequest}/confirm',[ITJobRequestController::class, 'confirmCompletion']);
    Route::post('/it-job-requests/{jobRequest}/sign-completion', [ITJobRequestController::class, 'signCompletion'])->name('jobrequests.sign-completion');

    Route::get('/itjr/{jobRequest}/division-chief/{action}',[ITJobRequestController::class, 'approveByDivisionChief'])->name('itjr.dc.action');
    

    // Division Chief In-App Approval Dashboards
    Route::get('/vehicle-requests/dc-approval',   [\App\Http\Controllers\VehicleRequestController::class,  'divisionChiefApproval'])->name('vehicle-requests.dc-approval')->middleware('permission:vehicles.dc-approve');
    Route::get('/facility-requests/dc-approval',  [\App\Http\Controllers\FacilityRequestController::class, 'divisionChiefApproval'])->name('facility-requests.dc-approval')->middleware('permission:facilities.dc-approve');
    Route::get('/service-requests/dc-approval',   [\App\Http\Controllers\ServiceRequestController::class,  'divisionChiefApproval'])->name('service-requests.dc-approval')->middleware('permission:facilities.dc-approve');

    // FAD In-App Approval Dashboards
    Route::get('/facility-requests/fad-approval',                          [\App\Http\Controllers\FacilityRequestController::class, 'fadApproval'])->name('facility-requests.fad-approval')->middleware('permission:facilities.fad-approve');
    Route::post('/facility-requests/{facilityRequest}/fad-action',         [\App\Http\Controllers\FacilityRequestController::class, 'fadAction'])->name('facility-requests.fad-action')->middleware('permission:facilities.fad-approve');
    Route::post('/work-requests/{workRequest}/fad-action',                 [\App\Http\Controllers\WorkRequestController::class,     'fadAction'])->name('work-requests.fad-action')->middleware('permission:facilities.fad-approve');
    Route::get('/service-requests/fad-approval',                           [\App\Http\Controllers\ServiceRequestController::class,  'fadApproval'])->name('service-requests.fad-approval')->middleware('permission:facilities.fad-approve');
    Route::post('/service-requests/{serviceRequest}/fad-action',           [\App\Http\Controllers\ServiceRequestController::class,  'fadAction'])->name('service-requests.fad-action')->middleware('permission:facilities.fad-approve');

    // Vehicle FAD Approval Dashboard
    Route::get('/vehicle-requests/fad-approval', [VehicleRequestController::class, 'fadApproval'])->name('vehicle-requests.fad-approval');
    Route::post('/vehicle-requests/{vehicleRequest}/fad-action', [VehicleRequestController::class, 'fadApproveInApp'])->name('vehicle-requests.fad-action');

    // OCD Approval Dashboards
    Route::get('/vehicle-requests/ocd-approval', [VehicleRequestController::class, 'ocdApproval'])->name('vehicle-requests.ocd-approval');
    Route::post('/vehicle-requests/{vehicleRequest}/ocd-action', [VehicleRequestController::class, 'approveByOCDInApp'])->name('vehicle-requests.ocd-action');
    Route::get('/facility-requests/ocd-approval', [\App\Http\Controllers\FacilityRequestController::class, 'ocdApproval'])->name('facility-requests.ocd-approval');
    Route::post('/facility-requests/{facilityRequest}/ocd-action', [\App\Http\Controllers\FacilityRequestController::class, 'ocdAction'])->name('facility-requests.ocd-action');
    Route::get('/messengerial/ocd-approval', [\App\Http\Controllers\MessengerialController::class, 'ocdApproval'])->name('messengerial.ocd-approval');
    Route::post('/messengerial/{messengerialRequest}/ocd-action', [\App\Http\Controllers\MessengerialController::class, 'ocdAction'])->name('messengerial.ocd-action');
    Route::get('/hr/gatepass/ocd-approval', [\App\Http\Controllers\HumanResource\GatePassController::class, 'ocdApproval'])->name('gatepass.ocd-approval');
    Route::post('/hr/gatepass/{id}/ocd-action', [\App\Http\Controllers\HumanResource\GatePassController::class, 'approveByOCDInApp'])->name('gatepass.ocd-action');

    // Vehicle Requests
    Route::get('/vehicle-requests', [VehicleRequestController::class, 'index'])->name('vehicle-requests.index');
    Route::post('/vehicle-requests', [VehicleRequestController::class, 'store'])->name('vehicle-requests.store');
    Route::post('/vehicle-requests/{vehicleRequest}/approve', [\App\Http\Controllers\VehicleRequestController::class, 'approveInApp'])->name('vehicle-requests.approve.inapp')->middleware('permission:vehicles.dc-approve');
    Route::post('/vehicle-requests/{vehicleRequest}/decline', [\App\Http\Controllers\VehicleRequestController::class, 'declineInApp'])->name('vehicle-requests.decline.inapp')->middleware('permission:vehicles.dc-approve');
    Route::post('/vehicle-requests/{vehicleRequest}/sign-completion', [\App\Http\Controllers\VehicleRequestController::class, 'signCompletion'])->name('vehicle-requests.sign-completion');
    Route::post('/facility-requests/{facilityRequest}/sign-completion', [\App\Http\Controllers\FacilityRequestController::class, 'signCompletion'])->name('facility-requests.sign-completion');
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
    Route::post('/facility-requests/{facilityRequest}/approve', [\App\Http\Controllers\FacilityRequestController::class, 'approveInApp'])->name('facility-requests.approve.inapp')->middleware('permission:facilities.dc-approve');
    Route::post('/facility-requests/{facilityRequest}/decline', [\App\Http\Controllers\FacilityRequestController::class, 'declineInApp'])->name('facility-requests.decline.inapp')->middleware('permission:facilities.dc-approve');
    // Work Requests (General Services)
    Route::get('/work-requests', [WorkRequestController::class, 'index'])->name('work-requests.index')->middleware('permission:facilities.view');
    Route::post('/work-requests', [WorkRequestController::class, 'store'])->name('work-requests.store')->middleware('permission:facilities.create');
    Route::put('/work-requests/{workRequest}', [WorkRequestController::class, 'update'])->name('work-requests.update')->middleware('permission:facilities.create');
    Route::delete('/work-requests/{workRequest}', [WorkRequestController::class, 'destroy'])->name('work-requests.destroy')->middleware('permission:facilities.create');

    // Completion endpoint — GSU Head / Admin (facilities.manage)
    Route::post('/work-requests/{workRequest}/complete', [WorkRequestController::class, 'complete'])
        ->name('work-requests.complete')
        ->middleware('permission:facilities.manage');
    Route::post('/work-requests/{workRequest}/pre-repair-inspection', [\App\Http\Controllers\WorkRequestPreRepairInspectionController::class, 'save'])
        ->name('work-requests.pre-repair-inspection.save')
        ->middleware('permission:facilities.view');
    Route::post('/work-requests/{workRequest}/pre-repair-inspection/submit', [\App\Http\Controllers\WorkRequestPreRepairInspectionController::class, 'submit'])
        ->name('work-requests.pre-repair-inspection.submit')
        ->middleware('permission:facilities.view');
    Route::post('/work-requests/{workRequest}/pre-repair-inspection/note', [\App\Http\Controllers\WorkRequestPreRepairInspectionController::class, 'note'])
        ->name('work-requests.pre-repair-inspection.note')
        ->middleware('permission:facilities.manage');
    Route::post('/work-requests/{workRequest}/pre-repair-inspection/return', [\App\Http\Controllers\WorkRequestPreRepairInspectionController::class, 'returnForRevision'])
        ->name('work-requests.pre-repair-inspection.return')
        ->middleware('permission:facilities.manage');
    Route::get('/work-requests/{workRequest}/pre-repair-inspection/print', [\App\Http\Controllers\WorkRequestPreRepairInspectionController::class, 'print'])
        ->name('work-requests.pre-repair-inspection.print')
        ->middleware('permission:facilities.view');

    // Print view for a single work request (printable slip)

    // Guidance — Dashboard & analytics
    Route::get('/guidance/dashboard', [\App\Http\Controllers\GuidanceConsultationController::class, 'dashboard'])->name('guidance.dashboard')->middleware('permission:guidance.view');

    // Guidance — Transaction report (date-range)
    Route::get('/guidance/reports', [\App\Http\Controllers\GuidanceConsultationController::class, 'transactionReport'])->name('guidance.reports')->middleware('permission:guidance.view');

    // Guidance — Consultations list (Guidance / Administrator only)
    Route::get('/guidance/consultations', [\App\Http\Controllers\GuidanceConsultationController::class, 'index'])->name('guidance.consultations.index')->middleware('permission:guidance.view');
    // Guidance — Referral page for Faculty / Staff
    Route::get('/guidance/refer', [\App\Http\Controllers\GuidanceConsultationController::class, 'referPage'])->name('guidance.refer')->middleware('permission:guidance.refer');
    Route::get('/guidance/students/search', [\App\Http\Controllers\GuidanceConsultationController::class, 'searchStudents'])->name('guidance.students.search')->middleware('permission:guidance.refer');
    Route::post('/guidance/referrals', [\App\Http\Controllers\GuidanceConsultationController::class, 'storeReferral'])->name('guidance.referrals.store')->middleware('permission:guidance.refer');
    Route::post('/guidance/consultations/{consultation}/assign', [\App\Http\Controllers\GuidanceConsultationController::class, 'assign'])->name('guidance.consultations.assign')->middleware('permission:guidance.manage');
    Route::get('/guidance/consultations/{consultation}/admission-slip', [\App\Http\Controllers\GuidanceConsultationController::class, 'admissionSlip'])->name('guidance.consultations.admission-slip')->middleware('permission:guidance.manage');
    // Save intervention details (Guidance personnel only)
    Route::get('/guidance/consultations/{consultation}/intervention', [\App\Http\Controllers\GuidanceConsultationController::class, 'getIntervention'])->name('guidance.consultations.intervention.get')->middleware('permission:guidance.manage');
    Route::post('/guidance/consultations/{consultation}/intervention', [\App\Http\Controllers\GuidanceConsultationController::class, 'intervention'])->name('guidance.consultations.intervention')->middleware('permission:guidance.manage');

    // Guidance — Session Reports
    Route::get('/guidance/session-reports', [\App\Http\Controllers\GuidanceSessionReportController::class, 'index'])->name('guidance.session-reports.index')->middleware('permission:guidance.manage');
    Route::post('/guidance/session-reports', [\App\Http\Controllers\GuidanceSessionReportController::class, 'store'])->name('guidance.session-reports.store')->middleware('permission:guidance.manage');
    Route::put('/guidance/session-reports/{sessionReport}', [\App\Http\Controllers\GuidanceSessionReportController::class, 'update'])->name('guidance.session-reports.update')->middleware('permission:guidance.manage');
    Route::get('/guidance/session-reports/{sessionReport}/print', [\App\Http\Controllers\GuidanceSessionReportController::class, 'print'])->name('guidance.session-reports.print')->middleware('permission:guidance.manage');

    // ── EGCU Student Cumulative Records ───────────────────────────────────────
    Route::get('/guidance/cumulative-records', [\App\Http\Controllers\Guidance\CumulativeRecordController::class, 'index'])
        ->name('guidance.cumulative.index')
        ->middleware('permission:guidance.cumulative.view');
    Route::get('/guidance/cumulative-records/{pisaySystemID}', [\App\Http\Controllers\Guidance\CumulativeRecordController::class, 'show'])
        ->name('guidance.cumulative.show')
        ->middleware('permission:guidance.cumulative.view')
        ->where('pisaySystemID', '[A-Za-z0-9\-]+');

    // ── Student Medical Records ───────────────────────────────────────────────
    Route::get('/students/health', [\App\Http\Controllers\StudentHealth\StudentHealthController::class, 'index'])
        ->name('students.health.index')
        ->middleware('permission:students.health.view');
    Route::get('/students/health/{pisaySystemID}', [\App\Http\Controllers\StudentHealth\StudentHealthController::class, 'show'])
        ->name('students.health.show')
        ->middleware('permission:students.health.view')
        ->where('pisaySystemID', '[A-Za-z0-9\-]+');

    Route::get('/work-requests/{workRequest}/print', [WorkRequestController::class, 'print'])
        ->name('work-requests.print')
        ->middleware('permission:facilities.manage');

    // Division chief approve/decline via signed links for Work Requests
    Route::get('/work-requests/{workRequest}/approve/{chief}', [\App\Http\Controllers\WorkRequestController::class, 'approveByDivisionChief'])
        ->name('work-requests.approve')
        ->middleware(['signed']);

    // Authenticated in-app approve/decline
    Route::post('/work-requests/{workRequest}/approve', [\App\Http\Controllers\WorkRequestController::class, 'approveInApp'])->name('work-requests.approve.inapp')->middleware('permission:facilities.dc-approve');
    Route::post('/work-requests/{workRequest}/decline', [\App\Http\Controllers\WorkRequestController::class, 'declineInApp'])->name('work-requests.decline.inapp')->middleware('permission:facilities.dc-approve');

    // Authenticated in-app approve/decline by GSU Head
    Route::post('/work-requests/{workRequest}/gsu-approve', [\App\Http\Controllers\WorkRequestController::class, 'approveInAppGSU'])->name('work-requests.gsu-approve.inapp')->middleware('permission:facilities.manage');
    Route::post('/work-requests/{workRequest}/gsu-decline', [\App\Http\Controllers\WorkRequestController::class, 'declineInAppGSU'])->name('work-requests.gsu-decline.inapp')->middleware('permission:facilities.manage');

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
    Route::post('/service-requests/{serviceRequest}/approve', [\App\Http\Controllers\ServiceRequestController::class, 'approveInApp'])->name('service-requests.approve.inapp')->middleware('permission:facilities.dc-approve');
    Route::post('/service-requests/{serviceRequest}/decline', [\App\Http\Controllers\ServiceRequestController::class, 'declineInApp'])->name('service-requests.decline.inapp')->middleware('permission:facilities.dc-approve');
    Route::put('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'update'])->name('service-requests.update');
    Route::delete('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'destroy'])->name('service-requests.destroy');
    // Procurement (PR, ORS, DV) — see routes/procurement.php
    // Driver assignment API
    Route::get('/api/drivers', [\App\Http\Controllers\DriverController::class, 'index'])->name('api.drivers.index');
    Route::post('/vehicle-requests/{vehicleRequest}/assign-driver', [\App\Http\Controllers\DriverController::class, 'assign'])->name('vehicle-requests.assign-driver');
    // Division chief approval via signed link
    // Signed approval link: includes the approver id so the link can be used from email
    Route::get('/vehicle-requests/{vehicleRequest}/approve/{chief}', [VehicleRequestController::class, 'approveByDivisionChief'])
        ->name('vehicle-requests.approve')
        ->middleware(['signed']);

    // FAD approval via signed link (sent to FAD users)
    Route::get('/vehicle-requests/{vehicleRequest}/fad/approve/{fad}', [VehicleRequestController::class, 'fadApproveByEmail'])
        ->name('vehicle-requests.fad.approve')
        ->middleware(['signed']);

    Route::get('/vehicle-requests/{vehicleRequest}/fad/decline/{fad}', [VehicleRequestController::class, 'fadDeclineByEmail'])
        ->name('vehicle-requests.fad.decline')
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

    // Print trip ticket
    Route::get('/vehicle-requests/{vehicleRequest}/print', [VehicleRequestController::class, 'printTicket'])
        ->name('vehicle-requests.print')
        ->middleware('permission:vehicles.manage');

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

    // Print service request
    Route::get('/service-requests/{serviceRequest}/print', [\App\Http\Controllers\ServiceRequestController::class, 'printTicket'])
        ->name('service-requests.print')
        ->middleware('permission:facilities.manage');

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

    // Upload proof of delivery (base64 JSON — Cloudflare blocks multipart)
    Route::post('/messengerial/{messengerialRequest}/upload-proof', [\App\Http\Controllers\MessengerialController::class, 'uploadProof'])
        ->name('messengerial.upload_proof')
        ->middleware('permission:documents.approve');

    // View/stream proof PDF from S3 (authenticated proxy — S3 bucket is private)
    Route::get('/messengerial/{messengerialRequest}/proof', [\App\Http\Controllers\MessengerialController::class, 'viewProof'])
        ->name('messengerial.proof');

    // ── Document Tracking System ──────────────────────────────────────────────
    Route::prefix('document-tracking')->name('document-tracking.')->group(function () {
        // Static/prefix routes MUST come before the /{document} wildcard
        Route::get('/',    [\App\Http\Controllers\DocumentTrackingController::class, 'index'])->name('index');
        Route::post('/',   [\App\Http\Controllers\DocumentTrackingController::class, 'store'])->name('store');

        // Routing step actions (static path segment "routings" — before wildcard)
        Route::post('/routings/{routing}/receive', [\App\Http\Controllers\DocumentTrackingController::class, 'receive'])->name('receive');
        Route::post('/routings/{routing}/act',     [\App\Http\Controllers\DocumentTrackingController::class, 'act'])->name('act');
        Route::post('/routings/{routing}/forward', [\App\Http\Controllers\DocumentTrackingController::class, 'forward'])->name('forward');
        Route::post('/routings/{routing}/return',  [\App\Http\Controllers\DocumentTrackingController::class, 'returnDoc'])->name('return');
        Route::post('/routings/{routing}/review',  [\App\Http\Controllers\DocumentTrackingController::class, 'review'])->name('review');

        // Document Type management ("types" prefix — before wildcard)
        Route::prefix('types')->name('types.')->group(function () {
            Route::get('/',    [\App\Http\Controllers\DocumentTypeController::class, 'index'])->name('index');
            Route::post('/',   [\App\Http\Controllers\DocumentTypeController::class, 'store'])->name('store');
            Route::put('/{documentType}',   [\App\Http\Controllers\DocumentTypeController::class, 'update'])->name('update');
            Route::delete('/{documentType}',[\App\Http\Controllers\DocumentTypeController::class, 'destroy'])->name('destroy');
            Route::post('/{documentType}/steps',  [\App\Http\Controllers\DocumentTypeController::class, 'storeStep'])->name('steps.store');
            Route::post('/{documentType}/reorder',[\App\Http\Controllers\DocumentTypeController::class, 'reorderSteps'])->name('steps.reorder');
            Route::put('/steps/{step}',    [\App\Http\Controllers\DocumentTypeController::class, 'updateStep'])->name('steps.update');
            Route::delete('/steps/{step}', [\App\Http\Controllers\DocumentTypeController::class, 'destroyStep'])->name('steps.destroy');
        });

        // Wildcard /{document} LAST — after all static segments
        Route::get('/{document}',                   [\App\Http\Controllers\DocumentTrackingController::class, 'show'])->name('show');
        Route::put('/{document}',                   [\App\Http\Controllers\DocumentTrackingController::class, 'update'])->name('update');
        Route::post('/{document}/complete',          [\App\Http\Controllers\DocumentTrackingController::class, 'complete'])->name('complete');
        Route::post('/{document}/annotate',          [\App\Http\Controllers\DocumentTrackingController::class, 'annotate'])->name('annotate');
        Route::get('/{document}/scan/{attachment}',  [\App\Http\Controllers\DocumentTrackingController::class, 'viewScan'])->name('scan');
    });

    // Messengerial CRUD routes (any authenticated user may create; controller enforces edit/delete rules)
    Route::get('/messengerial', [\App\Http\Controllers\MessengerialController::class, 'index'])->name('messengerial.index');
    Route::post('/messengerial', [\App\Http\Controllers\MessengerialController::class, 'store'])->name('messengerial.store');
    Route::put('/messengerial/{messengerialRequest}', [\App\Http\Controllers\MessengerialController::class, 'update'])->name('messengerial.update');
    Route::delete('/messengerial/{messengerialRequest}', [\App\Http\Controllers\MessengerialController::class, 'destroy'])->name('messengerial.destroy');

    // Messengerial — Division Chief in-app approval
    Route::middleware(['auth', 'permission:messengerial.dc-approve'])->group(function () {
        Route::get('/messengerial/for-approval', [\App\Http\Controllers\MessengerialController::class, 'forApproval'])
            ->name('messengerial.for-approval');
        Route::post('/messengerial/{messengerialRequest}/division-chief-action', [\App\Http\Controllers\MessengerialController::class, 'divisionChiefAction'])
            ->name('messengerial.division-chief-action');
    });

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

    // Print facility request
    Route::get('/facility-requests/{facilityRequest}/print', [\App\Http\Controllers\FacilityRequestController::class, 'printTicket'])
        ->name('facility-requests.print')
        ->middleware('permission:facilities.manage');

    Route::post('/vehicle-requests/{vehicleRequest}/decline/{chief}', [VehicleRequestController::class, 'submitDecline'])
        ->name('vehicle-requests.decline.submit')
        ->middleware(['signed']);
    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::put('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'update'])->name('vehicle-requests.update');
        Route::delete('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'destroy'])->name('vehicle-requests.destroy');
    });
    Route::middleware('permission:facilities.manage')->group(function () {
        Route::put('/facility-requests/{facilityRequest}', [\App\Http\Controllers\FacilityRequestController::class, 'update'])->name('facility-requests.update');
        Route::delete('/facility-requests/{facilityRequest}', [\App\Http\Controllers\FacilityRequestController::class, 'destroy'])->name('facility-requests.destroy');
    });

    // Only MIS/Admin can assess requests
    Route::post('/job-requests/{jobRequest}/assess', [ITJobRequestController::class, 'assess'])
        ->middleware('permission:it.requests.manage')
        ->name('jobrequests.assess');

    Route::put('/job-requests/{itJobRequest}/update', [ITJobRequestController::class, 'update'])
    ->name('job-requests.update');

    Route::get('/ict-equipments', [ICTEquipmentController::class, 'index'])->name('ict-equipments.index');
    Route::get('/atlas-sentinel/health-dashboard', [ICTEquipmentController::class, 'healthDashboard'])
        ->middleware('permission:it.equipment.view')
        ->name('atlas-sentinel.health-dashboard');
    Route::get('/atlas-sentinel/health-dashboard/{device}/history', [ICTEquipmentController::class, 'healthHistory'])
        ->middleware('permission:it.equipment.view')
        ->name('atlas-sentinel.health-dashboard.history');
    Route::get('/atlas-sentinel/health-dashboard/{device}/specs', [ICTEquipmentController::class, 'healthSpecs'])
        ->middleware('permission:it.equipment.view')
        ->name('atlas-sentinel.health-dashboard.specs');

    // Atlas Sentinel remote-help queue (attended RustDesk sessions)
    Route::middleware('permission:atlas.sentinel.remote-help.manage')->group(function () {
        Route::get('/atlas-sentinel/remote-help', [\App\Http\Controllers\AtlasSentinelRemoteHelpController::class, 'index'])
            ->name('atlas-sentinel.remote-help.index');
        Route::get('/atlas-sentinel/remote-help/poll', [\App\Http\Controllers\AtlasSentinelRemoteHelpController::class, 'poll'])
            ->name('atlas-sentinel.remote-help.poll');
        Route::post('/atlas-sentinel/remote-help/{helpRequest}/claim', [\App\Http\Controllers\AtlasSentinelRemoteHelpController::class, 'claim'])
            ->name('atlas-sentinel.remote-help.claim');
        Route::post('/atlas-sentinel/remote-help/{helpRequest}/reveal', [\App\Http\Controllers\AtlasSentinelRemoteHelpController::class, 'reveal'])
            ->name('atlas-sentinel.remote-help.reveal');
        Route::post('/atlas-sentinel/remote-help/{helpRequest}/end', [\App\Http\Controllers\AtlasSentinelRemoteHelpController::class, 'end'])
            ->name('atlas-sentinel.remote-help.end');
    });

    // Atlas Sentinel document backups — schedule management + status
    Route::get('/atlas-sentinel/backups', [\App\Http\Controllers\AtlasSentinelBackupController::class, 'index'])
        ->middleware('permission:it.equipment.view')
        ->name('atlas-sentinel.backups.index');
    Route::post('/atlas-sentinel/backups', [\App\Http\Controllers\AtlasSentinelBackupController::class, 'store'])
        ->middleware('permission:it.equipment.manage')
        ->name('atlas-sentinel.backups.store');
    Route::put('/atlas-sentinel/backups/{schedule}', [\App\Http\Controllers\AtlasSentinelBackupController::class, 'update'])
        ->middleware('permission:it.equipment.manage')
        ->name('atlas-sentinel.backups.update');
    Route::delete('/atlas-sentinel/backups/{schedule}', [\App\Http\Controllers\AtlasSentinelBackupController::class, 'destroy'])
        ->middleware('permission:it.equipment.manage')
        ->name('atlas-sentinel.backups.destroy');
    Route::post('/atlas-sentinel/backups/{schedule}/run-now', [\App\Http\Controllers\AtlasSentinelBackupController::class, 'runNow'])
        ->middleware('permission:it.equipment.manage')
        ->name('atlas-sentinel.backups.run-now');
    Route::post('/ict-equipments', [ICTEquipmentController::class, 'store'])->name('ict-equipments.store');
    Route::put('/ict-equipments/{ictEquipment}', [ICTEquipmentController::class, 'update'])
    ->name('ict-equipments.update');

    // Delete ICT Equipment
    Route::delete('/ict-equipments/{ictEquipment}', [ICTEquipmentController::class, 'destroy'])
        ->name('ict-equipments.destroy');

    Route::get('/equipment/{ictEquipment}', [ICTEquipmentController::class, 'publicShow'])
        ->name('equipment.public.show');
    Route::get('/equipment/{ictEquipment}/qr', [ICTEquipmentController::class, 'qrCode'])
        ->name('equipment.qr');
    Route::post('/ict-equipments/report/generate', [ICTEquipmentController::class, 'generateReport'])->name('ict-equipments.report.generate');

    Route::post('/ict-equipments/enrollment-token', [ICTEquipmentController::class, 'generateEnrollmentToken'])
        ->middleware('permission:it.equipment.manage')
        ->name('ict-equipments.enrollment-token');

    Route::get('/ict-equipments/enrollment-tokens', [ICTEquipmentController::class, 'listEnrollmentTokens'])
        ->middleware('permission:it.equipment.manage')
        ->name('ict-equipments.enrollment-tokens.index');

    Route::post('/ict-equipments/enrollment-token/{token}/revoke', [ICTEquipmentController::class, 'revokeEnrollmentToken'])
        ->middleware('permission:it.equipment.manage')
        ->name('ict-equipments.enrollment-token.revoke');

    Route::post('/ict-equipments/{ictEquipment}/remediate', [ICTEquipmentController::class, 'remediate'])
        ->middleware('permission:it.equipment.manage')
        ->name('ict-equipments.remediate');

    Route::post('/ict-equipments/{ictEquipment}/merge', [ICTEquipmentController::class, 'merge'])
        ->middleware('permission:it.equipment.manage')
        ->name('ict-equipments.merge');

    Route::get('/computer-labs', [ComputerLabController::class, 'index'])
        ->middleware('permission:it.equipment.view|computer_labs.book|computer_labs.manage')
        ->name('computer-labs.index');
    Route::post('/computer-labs/bookings', [ComputerLabController::class, 'storeBooking'])
        ->middleware('permission:computer_labs.book|computer_labs.manage')
        ->name('computer-labs.bookings.store');
    Route::post('/computer-labs/bookings/{booking}/approve', [ComputerLabController::class, 'approveBooking'])
        ->middleware('permission:computer_labs.manage|faculty_loading.manage')
        ->name('computer-labs.bookings.approve');
    Route::post('/computer-labs/bookings/{booking}/reject', [ComputerLabController::class, 'rejectBooking'])
        ->middleware('permission:computer_labs.manage|faculty_loading.manage')
        ->name('computer-labs.bookings.reject');
    Route::post('/computer-labs/bookings/{booking}/cancel', [ComputerLabController::class, 'cancelBooking'])
        ->middleware('permission:computer_labs.book|computer_labs.manage|faculty_loading.manage')
        ->name('computer-labs.bookings.cancel');
    Route::patch('/computer-labs/bookings/{booking}/room', [ComputerLabController::class, 'moveBooking'])
        ->middleware('permission:computer_labs.manage|faculty_loading.manage')
        ->name('computer-labs.bookings.move');
    Route::post('/computer-labs/synchronize', [ComputerLabController::class, 'synchronize'])
        ->middleware('permission:computer_labs.manage|faculty_loading.manage')
        ->name('computer-labs.synchronize');
    Route::post('/computer-labs/schedule-approvals/{term}/submit', [ComputerLabScheduleApprovalController::class, 'submit'])
        ->name('computer-labs.schedule-approvals.submit');
    Route::post('/computer-labs/schedule-approvals/{approval}/approve', [ComputerLabScheduleApprovalController::class, 'approve'])
        ->name('computer-labs.schedule-approvals.approve');
    Route::post('/computer-labs/schedule-approvals/{approval}/return', [ComputerLabScheduleApprovalController::class, 'returnForRevision'])
        ->name('computer-labs.schedule-approvals.return');
    Route::get('/computer-labs/print', [ComputerLabController::class, 'printSchedule'])
        ->middleware('permission:it.equipment.view|computer_labs.book|computer_labs.manage')
        ->name('computer-labs.print');
    Route::get('/computer-labs/{room}', [ComputerLabController::class, 'show'])
        ->middleware('permission:it.equipment.view')
        ->name('computer-labs.show');
    Route::patch('/computer-labs/equipment/{equipment}/seat', [ComputerLabController::class, 'updateSeat'])
        ->middleware('permission:it.equipment.manage')
        ->name('computer-labs.update-seat');

    // PMS History routes
    Route::post('/ict-pms-history', [ICTPMSHistoryController::class, 'store'])->name('ict-pms-history.store');

    Route::get('/ict-pms', [PMSController::class, 'index'])->name('ict-pms.index');
    Route::post('/ict-pms', [PMSController::class, 'store'])->name('ict-pms.store');
    Route::get('/ict-pms/{id}', [PMSController::class, 'show'])->name('ict-pms.show');
    Route::put('/ict-pms/{pms}', [PMSController::class, 'update'])->name('ict-pms.update');
    Route::delete('/ict-pms/{pms}', [PMSController::class, 'destroy'])->name('ict-pms.destroy');

    // Assign multiple equipment to PMS
    Route::post('/ict-pms/{pmsId}/assign-equipments', [PMSController::class, 'assignEquipments'])->name('ict-pms.assign-equipments');
    Route::get('/ict-pms/{pms}/equipments', [PMSController::class, 'showEquipments'])->name('ict-pms.show-equipments');
    
    // Vehicles & Facilities management
    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
    });
    Route::middleware('permission:facilities.manage')->group(function () {
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
    
    Route::middleware(['auth', 'permission:it.requests.manage'])->group(function () {
    Route::get('/job-requests/for-approval', [ITJobRequestController::class, 'forApproval'])
        ->name('it.job-requests.for-approval');

    Route::post('/job-requests/{jobRequest}/division-chief-action', [ITJobRequestController::class, 'approveByDivisionChief'])
        ->name('job-requests.division-chief-action');
    });
    /*
    |--------------------------------------------------------------------------
    | OCD Approval Routes
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(['auth', 'permission:it.requests.manage'])->group(function () {
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
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{id}', [UserController::class, 'update'])->name('users.update')->middleware('permission:hr.employees.manage');
        Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/upload-signature', [UserController::class, 'uploadSignature'])->name('users.upload_signature');
        Route::get('/users/inactive', [UserController::class, 'inactiveIndex'])->name('users.inactive')->middleware('permission:hr.employees.manage');
        Route::post('/users/{id}/activate', [UserController::class, 'activate'])->name('users.activate')->middleware('permission:hr.employees.manage');
        Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');
    });

    // Roles & Divisions — was nested inside the users.view group above (wrong
    // permission inherited); split out so it's gated on its own permission.
    Route::middleware('permission:roles.assign')->group(function () {
        Route::get('/users-roles', [RolesController::class, 'index'])->name('roles.index');
        Route::post('users-roles', [RolesController::class, 'store'])->name('roles.store');
        Route::put('users-roles/{id}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('users-roles/{id}', [RolesController::class, 'destroy'])->name('roles.destroy');

        Route::get('/users-division', [RolesController::class, 'showDivisions'])->name('roles.divisions');
        Route::post('users-divisions', [RolesController::class, 'storeDivision'])->name('roles.divisions_store');
        Route::put('users-divisions/{id}', [RolesController::class, 'updateDivision'])->name('roles.division_update');
        Route::post('users-divisions/{division}/upload-signature', [RolesController::class, 'uploadSignature'])->name('roles.divisions.upload_signature');
    });

    // Reports — same reason, split out of the users.view group.
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports.index');
    });
    Route::get('/reports/audit-logs', [\App\Http\Controllers\Reports\AuditLogController::class, 'index'])->name('reports.audit_logs')->middleware('permission:roles.assign');

    // Agency Org Outcome — same reason, split out of the users.view group.
    Route::middleware('permission:ipcr.view')->group(function () {
        Route::get('/agency-outcomes', [AgencyOutcomeController::class, 'index'])->name('outcome.index');
        Route::post('agency-outcomes', [AgencyOutcomeController::class, 'store'])->name('outcome.store');
        Route::put('agency-outcomes/{id}', [AgencyOutcomeController::class, 'update'])->name('outcome.update');
        Route::delete('agency-outcomes/{id}', [AgencyOutcomeController::class, 'destroy'])->name('outcome.destroy');
    });

// Performance Management — Committees & Special Assignments (open to any authenticated user; controller handles auth)
Route::middleware(['auth', 'pshs.email'])->group(function () {
    Route::get('/performance-management/committees', [\App\Http\Controllers\CommitteePerformanceController::class, 'index'])->name('pm-committees.index');
    Route::post('/performance-management/committees', [\App\Http\Controllers\CommitteePerformanceController::class, 'store'])->name('pm-committees.store');
    Route::put('/performance-management/committees/{committee}', [\App\Http\Controllers\CommitteePerformanceController::class, 'update'])->name('pm-committees.update');
    Route::delete('/performance-management/committees/{committee}', [\App\Http\Controllers\CommitteePerformanceController::class, 'destroy'])->name('pm-committees.destroy');
    Route::get('/performance-management/committees/{committee}', [\App\Http\Controllers\CommitteePerformanceController::class, 'show'])->name('pm-committees.show');
    Route::post('/performance-management/committees/{committee}/members/{member}/accomplishment', [\App\Http\Controllers\CommitteePerformanceController::class, 'saveMemberAccomplishment'])->name('pm-committees.member-accomplishment');
    Route::post('/performance-management/committees/{committee}/members/{member}/rate', [\App\Http\Controllers\CommitteePerformanceController::class, 'rateMember'])->name('pm-committees.rate-member');

    // Committee task board (shared by the PM and Faculty Loading committee pages)
    Route::post('/committees/{committee}/tasks', [\App\Http\Controllers\CommitteeTaskController::class, 'store'])->name('committee-tasks.store');
    Route::put('/committees/{committee}/tasks/reorder', [\App\Http\Controllers\CommitteeTaskController::class, 'reorder'])->name('committee-tasks.reorder');
    Route::put('/committee-tasks/{task}', [\App\Http\Controllers\CommitteeTaskController::class, 'update'])->name('committee-tasks.update');
    Route::put('/committee-tasks/{task}/status', [\App\Http\Controllers\CommitteeTaskController::class, 'updateStatus'])->name('committee-tasks.status');
    Route::post('/committee-tasks/{task}/updates', [\App\Http\Controllers\CommitteeTaskController::class, 'storeUpdate'])->name('committee-tasks.updates.store');
    Route::delete('/committee-tasks/{task}', [\App\Http\Controllers\CommitteeTaskController::class, 'destroy'])->name('committee-tasks.destroy');

    Route::get('/performance-management/special-assignments', [\App\Http\Controllers\SpecialAssignmentPerformanceController::class, 'index'])->name('pm-special-assignments.index');
    Route::post('/performance-management/special-assignments', [\App\Http\Controllers\SpecialAssignmentPerformanceController::class, 'store'])->name('pm-special-assignments.store');
    Route::put('/performance-management/special-assignments/{specialAssignment}', [\App\Http\Controllers\SpecialAssignmentPerformanceController::class, 'update'])->name('pm-special-assignments.update');
    Route::delete('/performance-management/special-assignments/{specialAssignment}', [\App\Http\Controllers\SpecialAssignmentPerformanceController::class, 'destroy'])->name('pm-special-assignments.destroy');
    Route::get('/performance-management/special-assignments/{specialAssignment}', [\App\Http\Controllers\SpecialAssignmentPerformanceController::class, 'show'])->name('pm-special-assignments.show');
    Route::post('/performance-management/special-assignments/{specialAssignment}/members/{member}/accomplishment', [\App\Http\Controllers\SpecialAssignmentPerformanceController::class, 'saveMemberAccomplishment'])->name('pm-special-assignments.member-accomplishment');
    Route::post('/performance-management/special-assignments/{specialAssignment}/members/{member}/rate', [\App\Http\Controllers\SpecialAssignmentPerformanceController::class, 'rateMember'])->name('pm-special-assignments.rate-member');

    // My Accomplishments
    Route::get('/performance-management/my-accomplishments', [\App\Http\Controllers\AccomplishmentController::class, 'index'])->name('my-accomplishments.index');
    Route::post('/performance-management/my-accomplishments', [\App\Http\Controllers\AccomplishmentController::class, 'store'])->name('my-accomplishments.store');
    Route::put('/performance-management/my-accomplishments/{accomplishment}', [\App\Http\Controllers\AccomplishmentController::class, 'update'])->name('my-accomplishments.update');
    Route::delete('/performance-management/my-accomplishments/{accomplishment}', [\App\Http\Controllers\AccomplishmentController::class, 'destroy'])->name('my-accomplishments.destroy');
    Route::post('/performance-management/my-accomplishments/{accomplishment}/photos', [\App\Http\Controllers\AccomplishmentController::class, 'uploadPhoto'])->name('my-accomplishments.upload-photo');
    Route::delete('/performance-management/my-accomplishments/photos/{photo}', [\App\Http\Controllers\AccomplishmentController::class, 'deletePhoto'])->name('my-accomplishments.delete-photo');
    Route::get('/performance-management/my-accomplishments/monthly-report', [\App\Http\Controllers\AccomplishmentController::class, 'monthlyReport'])->name('my-accomplishments.monthly-report');

    // My Unit (Unit Head IPCR review)
    Route::get('/performance-management/my-unit', [\App\Http\Controllers\MyUnitIPCRController::class, 'index'])->name('my-unit-ipcr.index');
    Route::get('/performance-management/my-unit/{id}', [\App\Http\Controllers\MyUnitIPCRController::class, 'show'])->name('my-unit-ipcr.show');

    // Rate accomplishments — open to any auth user; controller enforces per-plan authorization
    Route::put('/division-chief-employee-ipcr-plan/{ipcr}/{plan}/rate', [\App\Http\Controllers\DivisionChiefIPCRController::class, 'rateIPCRPlan'])->name('division-chief-employee-ipcr-plan.rateIPCRPlan');
});

// Lightweight users JSON endpoint for dropdowns (authenticated)
Route::middleware('auth')->get('/api/users/select', [UserController::class, 'selectList'])->name('users.select');

// ─── RBAC Admin Pages (Inertia) ───────────────────────────────────────────────
Route::middleware(['auth', 'permission:roles.assign'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/roles',       fn () => Inertia::render('Admin/Roles/Index'))->name('roles');
        Route::get('/permissions', fn () => Inertia::render('Admin/Roles/Permissions'))->name('permissions');
        Route::get('/assign-roles',fn () => Inertia::render('Admin/Users/AssignRoles'))->name('assign-roles');
    });
// ─────────────────────────────────────────────────────────────────────────────

// ─── RBAC Admin API ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:roles.assign'])
    ->prefix('admin/rbac')
    ->name('admin.rbac.')
    ->group(function () {
        // Roles
        Route::get('/roles',                                   [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles',                                  [\App\Http\Controllers\Admin\RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}',                            [\App\Http\Controllers\Admin\RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{role}',                            [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}',                         [\App\Http\Controllers\Admin\RoleController::class, 'destroy'])->name('roles.destroy');
        Route::put('/roles/{role}/permissions',                [\App\Http\Controllers\Admin\RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
        Route::get('/permissions-all',                         [\App\Http\Controllers\Admin\RoleController::class, 'allPermissions'])->name('permissions.all');

        // Permissions
        Route::get('/permissions',                             [\App\Http\Controllers\Admin\PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions',                            [\App\Http\Controllers\Admin\PermissionController::class, 'store'])->name('permissions.store');
        Route::put('/permissions/{permission}',                [\App\Http\Controllers\Admin\PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}',             [\App\Http\Controllers\Admin\PermissionController::class, 'destroy'])->name('permissions.destroy');

        // User → Role assignment
        Route::get('/users',                                   [\App\Http\Controllers\Admin\UserRoleController::class, 'index'])->name('users.index');
        Route::get('/users/{user}',                            [\App\Http\Controllers\Admin\UserRoleController::class, 'show'])->name('users.show');
        Route::put('/users/{user}/roles',                      [\App\Http\Controllers\Admin\UserRoleController::class, 'sync'])->name('users.roles.sync');
        Route::get('/roles-list',                              [\App\Http\Controllers\Admin\UserRoleController::class, 'rolesList'])->name('roles.list');
    });
// ─────────────────────────────────────────────────────────────────────────────

// HR Employees page
Route::middleware(['auth','permission:hr.employees.manage'])->get('/hr/employees', [UserController::class, 'employeesIndex'])->name('hr.employees.index');
Route::middleware(['auth','permission:hr.employees.manage'])->post('/hr/employees', [UserController::class, 'employeesStore'])->name('hr.employees.store');
Route::middleware(['auth','permission:hr.employees.manage'])->patch('/hr/employees/{user}/salary-grade', [UserController::class, 'assignSalaryGrade'])->name('hr.employees.salary-grade');

    // Human Resource attendance viewer (scoped for Staff/Faculty)
    Route::middleware('auth')->get('/human-resource/attendance', [\App\Http\Controllers\HumanResource\AttendanceController::class, 'index'])->name('hr.attendance.index');

// ─── Work From Home (WFH) Module ─────────────────────────────────────────────
Route::middleware(['auth', 'permission:wfh.view'])->prefix('hr/wfh')->name('hr.wfh.')->group(function () {

    // Inertia dashboard page
    Route::get('/', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'index'])
        ->name('index');

    // Time In / Time Out (JSON API, called via axios)
    Route::post('/time-in',  [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'timeIn'])
        ->middleware('permission:wfh.time-in')
        ->name('time-in');
    Route::post('/time-out', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'timeOut'])
        ->middleware('permission:wfh.time-out')
        ->name('time-out');
    Route::post('/break-out', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'breakOut'])
        ->middleware('permission:wfh.time-in')
        ->name('break-out');
    Route::post('/break-in', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'breakIn'])
        ->middleware('permission:wfh.time-in')
        ->name('break-in');

    // Attendance records
    Route::get('/attendance',     [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'myAttendance'])
        ->name('attendance.index');
    Route::get('/attendance/{wfhAttendance}', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'show'])
        ->name('attendance.show');

    // Accomplishments
    Route::get('/accomplishments',                    [\App\Http\Controllers\HumanResource\WFHAccomplishmentController::class, 'index'])
        ->name('accomplishments.index');
    Route::post('/accomplishments',                   [\App\Http\Controllers\HumanResource\WFHAccomplishmentController::class, 'store'])
        ->middleware('permission:wfh.accomplishments.create')
        ->name('accomplishments.store');
    Route::put('/accomplishments/{wfhAccomplishment}',    [\App\Http\Controllers\HumanResource\WFHAccomplishmentController::class, 'update'])
        ->middleware('permission:wfh.accomplishments.create')
        ->name('accomplishments.update');
    Route::delete('/accomplishments/{wfhAccomplishment}', [\App\Http\Controllers\HumanResource\WFHAccomplishmentController::class, 'destroy'])
        ->middleware('permission:wfh.accomplishments.delete')
        ->name('accomplishments.destroy');

    // Monitoring — users with wfh.monitor permission only
    Route::get('/monitor',      [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'monitorPage'])
        ->middleware('permission:wfh.monitor')
        ->name('monitor.page');
    Route::get('/monitor/data', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'monitor'])
        ->middleware('permission:wfh.monitor')
        ->name('monitor');

    // Print views
    Route::get('/print/timelogs',       [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'printTimeLogs'])
        ->name('print.timelogs');
    Route::get('/print/accomplishments', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'printAccomplishments'])
        ->name('print.accomplishments');

    // Image proxy — streams Drive photos server-side (no client Google auth needed)
    Route::get('/photo/{fileId}', [\App\Http\Controllers\HumanResource\WFHAttendanceController::class, 'photo'])
        ->name('photo')
        ->where('fileId', '[a-zA-Z0-9_.=-]+');  // dot needed for s3.<base64url> format
});
// ─────────────────────────────────────────────────────────────────────────────

// ─── Online Time Punches (Facial Recognition) Module ─────────────────────────
Route::middleware(['auth', 'permission:hr.online-punch.record'])->prefix('hr/online-punch')->name('hr.online-punch.')->group(function () {
    Route::get('/', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'index'])
        ->name('index');

    Route::post('/challenge/start', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'challengeStart'])
        ->name('challenge.start');

    Route::post('/location-check', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'locationCheck'])
        ->name('location-check');

    Route::post('/punch', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'punch'])
        ->name('punch');

    Route::get('/my-punches', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'myPunches'])
        ->name('my-punches');

    // Monitoring — users with hr.online-punch.monitor permission only
    Route::get('/monitor', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'monitorPage'])
        ->middleware('permission:hr.online-punch.monitor')
        ->name('monitor.page');
    Route::get('/monitor/data', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'monitor'])
        ->middleware('permission:hr.online-punch.monitor')
        ->name('monitor');

    // Geofence zones — users with hr.online-punch.manage-geofence permission only
    Route::get('/geofence-zones', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'geofenceZones'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('geofence-zones.index');
    Route::post('/geofence-zones', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'storeGeofenceZone'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('geofence-zones.store');
    Route::put('/geofence-zones/{zone}', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'updateGeofenceZone'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('geofence-zones.update');
    Route::delete('/geofence-zones/{zone}', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'destroyGeofenceZone'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('geofence-zones.destroy');

    // Trusted networks — users with hr.online-punch.manage-geofence permission only
    Route::get('/network-rules', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'networkRules'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('network-rules.index');
    Route::post('/network-rules', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'storeNetworkRule'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('network-rules.store');
    Route::put('/network-rules/{rule}', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'updateNetworkRule'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('network-rules.update');
    Route::delete('/network-rules/{rule}', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'destroyNetworkRule'])
        ->middleware('permission:hr.online-punch.manage-geofence')
        ->name('network-rules.destroy');

    // Image proxy
    Route::get('/photo/{fileId}', [\App\Http\Controllers\HR\OnlineTimePunchController::class, 'photo'])
        ->name('photo')
        ->where('fileId', '[a-zA-Z0-9_.=-]+');
});

// ─── Face Enrollment Module ───────────────────────────────────────────────────
Route::middleware(['auth'])->prefix('hr/face-enrollment')->name('hr.face-enrollment.')->group(function () {
    // Employee self-enrollment
    Route::get('/self', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'selfEnrollPage'])
        ->middleware('permission:hr.face-enrollment.self')
        ->name('self');
    Route::post('/self', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'store'])
        ->middleware('permission:hr.face-enrollment.self')
        ->name('store');
    Route::get('/my-status', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'myStatus'])
        ->middleware('permission:hr.face-enrollment.self')
        ->name('my-status');

    // HR review queue
    Route::get('/', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'index'])
        ->middleware('permission:hr.face-enrollment.manage')
        ->name('index');
    Route::get('/data', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'data'])
        ->middleware('permission:hr.face-enrollment.manage')
        ->name('data');
    Route::post('/{enrollment}/approve', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'approve'])
        ->middleware('permission:hr.face-enrollment.manage')
        ->name('approve');
    Route::post('/{enrollment}/reject', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'reject'])
        ->middleware('permission:hr.face-enrollment.manage')
        ->name('reject');

    // Image proxy
    Route::get('/photo/{fileId}', [\App\Http\Controllers\HR\FaceEnrollmentController::class, 'photo'])
        ->name('photo')
        ->where('fileId', '[a-zA-Z0-9_.=-]+');
});
// ─────────────────────────────────────────────────────────────────────────────

// Library statistics printable report
Route::middleware('auth')->get('/library/statistics/report', [\App\Http\Controllers\LibraryAttendanceController::class, 'report'])->name('library.statistics.report');
    // Students CRUD (Registrar / public admin may use)
    Route::get('/students', [\App\Http\Controllers\StudentController::class, 'index'])->name('students.index');
    Route::get('/students/{id}/id-card', [\App\Http\Controllers\StudentController::class, 'idCard'])->name('students.id-card');
    Route::get('/students/create', [\App\Http\Controllers\StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [\App\Http\Controllers\StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{id}/edit', [\App\Http\Controllers\StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{id}', [\App\Http\Controllers\StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{id}', [\App\Http\Controllers\StudentController::class, 'destroy'])->name('students.destroy');
    Route::get('/students/{id}/photo', [\App\Http\Controllers\StudentController::class, 'proxyPhoto'])->name('students.photo');
    Route::post('/students/{id}/photo', [\App\Http\Controllers\StudentController::class, 'updatePhoto'])->name('students.photo.update');
    // Library attendance (authenticated view)
    Route::get('/library/attendance', [\App\Http\Controllers\LibraryAttendanceController::class, 'index'])
        ->name('library.attendance.index')
        ->middleware('permission:library.manage');

    // Library collections (CRUD for librarians/admins)
    Route::get('/library/collections', [\App\Http\Controllers\LibraryCollectionsController::class, 'index'])
        ->name('library.collections.index')
        ->middleware('permission:library.manage');
    Route::post('/library/collections', [\App\Http\Controllers\LibraryCollectionsController::class, 'store'])
        ->name('library.collections.store')
        ->middleware('permission:library.manage');
    Route::put('/library/collections/{id}', [\App\Http\Controllers\LibraryCollectionsController::class, 'update'])
        ->name('library.collections.update')
        ->middleware('permission:library.manage');
    Route::delete('/library/collections/{id}', [\App\Http\Controllers\LibraryCollectionsController::class, 'destroy'])
        ->name('library.collections.destroy')
        ->middleware('permission:library.manage');
    // Collection Categories (CRUD for librarians/admins)
    Route::get('/library/collection-categories', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'index'])
        ->name('library.collection-categories.index')
        ->middleware('permission:library.manage');
    Route::post('/library/collection-categories', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'store'])
        ->name('library.collection-categories.store')
        ->middleware('permission:library.manage');
    Route::put('/library/collection-categories/{id}', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'update'])
        ->name('library.collection-categories.update')
        ->middleware('permission:library.manage');
    Route::delete('/library/collection-categories/{id}', [\App\Http\Controllers\LibraryCollectionCategoriesController::class, 'destroy'])
        ->name('library.collection-categories.destroy')
        ->middleware('permission:library.manage');
    Route::middleware('permission:ipcr.view')->group(function () {

        // ── Performance Indicators ──────────────────────────────────────────
        Route::get('/performance-indicators', [PerformanceIndicatorController::class, 'index'])->name('performanceindicator.index');
        Route::post('performance-indicators', [PerformanceIndicatorController::class, 'store'])->name('performanceindicator.store');
        Route::put('performance-indicators/{performanceIndicator}', [PerformanceIndicatorController::class, 'update'])->name('performanceindicator.update');
        Route::delete('performance-indicators/{performanceIndicator}', [PerformanceIndicatorController::class, 'destroy'])->name('performanceindicator.destroy');

        // ── Work Distribution Plans ─────────────────────────────────────────
        Route::get('/work-distributions', [WorkDistributionPlanController::class, 'index'])->name('workdistribution.index');
        Route::post('work-distributions', [WorkDistributionPlanController::class, 'store'])->name('workdistribution.store');
        Route::put('work-distributions/{id}', [WorkDistributionPlanController::class, 'update'])->name('workdistribution.update');
        Route::delete('work-distributions/{id}', [WorkDistributionPlanController::class, 'destroy'])->name('workdistribution.destroy');

        // ── Employee IPCR ───────────────────────────────────────────────────
        Route::get('/employee-ipcr', [EmployeeIPCRController::class, 'index'])->name('employee-ipcr.index');
        Route::post('/employee-ipcr', [EmployeeIPCRController::class, 'store'])->name('employee-ipcr.store')->middleware('permission:ipcr.create');
        Route::put('/employee-ipcr/{employeeIPCR}', [EmployeeIPCRController::class, 'update'])->name('employee-ipcr.update')->middleware('permission:ipcr.update');
        Route::delete('/employee-ipcr/{employeeIPCR}', [EmployeeIPCRController::class, 'destroy'])->name('employee-ipcr.destroy')->middleware('permission:ipcr.update');
        Route::post('/employee-ipcr/{employeeIPCR}/plans', [EmployeeIPCRController::class, 'addPlans'])->name('employee-ipcr.addPlans')->middleware('permission:ipcr.update');
        Route::get('/employee-ipcr/{id}', [EmployeeIPCRController::class, 'show'])->name('employee-ipcr.show');
        Route::put('employee-ipcr-plan/{ipcr}/{plan}', [EmployeeIPCRController::class, 'updateSelfRating'])->name('employee-ipcr-plan.updateSelfRating')->middleware('permission:ipcr.update');
        Route::post('/employee-ipcr/{employeeIPCR}/submit-review', [EmployeeIPCRController::class, 'submitForReview'])->name('employee-ipcr.submitReview')->middleware('permission:ipcr.submit');
        Route::post('/employee-ipcr/{employeeIPCR}/submit-rating', [EmployeeIPCRController::class, 'submitForRating'])->name('employee-ipcr.submitRating')->middleware('permission:ipcr.submit');
        Route::delete('/employee-ipcr/{employeeIPCR}/plans/{plan}', [EmployeeIPCRController::class, 'removePlan'])->name('employee-ipcr.removePlan')->middleware('permission:ipcr.update');
        Route::post('/employee-ipcr/{employeeIPCR}/resubmit', [EmployeeIPCRController::class, 'resubmit'])->name('employee-ipcr.resubmit')->middleware('permission:ipcr.submit');
        Route::post('/employee-ipcr/{employeeIPCR}/pull-fl-accomplishments', [EmployeeIPCRController::class, 'pullFLAccomplishments'])->name('employee-ipcr.pullFL')->middleware('permission:ipcr.update');
        Route::post('/employee-ipcr/{employeeIPCR}/generate-faculty-targets', [EmployeeIPCRController::class, 'generateFacultyTargets'])->name('employee-ipcr.generateFacultyTargets')->middleware('permission:ipcr.update');
        Route::put('/employee-ipcr/{employeeIPCR}/plans/{plan}/individual-target', [EmployeeIPCRController::class, 'updateIndividualTarget'])->name('employee-ipcr.updateIndividualTarget')->middleware('permission:ipcr.update');

        // ── Division Chief IPCR ─────────────────────────────────────────────
        Route::get('/division-chief/ipcrs', [DivisionChiefIPCRController::class, 'index'])->name('division-chief-ipcr.index');
        Route::get('/division-chief-employee-ipcr/{id}', [DivisionChiefIPCRController::class, 'show'])->name('division-employee-ipcr.show');
        // savePlanRemark stays at ipcr.view — non-DC raters (unit heads / committee heads) use it; controller enforces the actor
        Route::put('/division-chief-employee-ipcr-plan/{ipcr}/{plan}/remark', [DivisionChiefIPCRController::class, 'savePlanRemark'])->name('division-chief-employee-ipcr-plan.remark');
        // Supervisor-stage actions stay at ipcr.view — Academic Unit Heads and
        // the ACIDAA (Faculty role, no ipcr.approve) act here for CID faculty;
        // IPCRWorkflowService::assertCanManage enforces the actual actor
        // (same precedent as rateIPCRPlan / savePlanRemark above).
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/targetsapproval', [DivisionChiefIPCRController::class, 'approveTargets'])->name('division-chief-employee-ipcr.targetsapproval');
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/disapprove', [DivisionChiefIPCRController::class, 'disapproveTargets'])->name('division-chief-employee-ipcr.disapprove');
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/return-accomplishment', [DivisionChiefIPCRController::class, 'returnAccomplishment'])->name('division-chief-employee-ipcr.returnAccomplishment');
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/saveratings', [DivisionChiefIPCRController::class, 'saveRatings'])->name('division-chief-employee-ipcr.saveratings');
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/save-comments', [DivisionChiefIPCRController::class, 'saveComments'])->name('division-chief-employee-ipcr.savecomments');

        // Coaching & Mentoring Journal — gated by IPCRWorkflowService::assertCanManage (immediate supervisor / OCD)
        Route::post('/division-chief-employee-ipcr/{employeeIPCR}/coaching-sessions', [\App\Http\Controllers\IPCRCoachingSessionController::class, 'store'])->name('ipcr-coaching-sessions.store');
        Route::delete('/division-chief-employee-ipcr/{employeeIPCR}/coaching-sessions/{coachingSession}', [\App\Http\Controllers\IPCRCoachingSessionController::class, 'destroy'])->name('ipcr-coaching-sessions.destroy');

        // Ministerial / endorsement actions — Division Chiefs only
        Route::middleware('permission:ipcr.approve')->group(function () {
            Route::post('/division-chief-employee-ipcr/{employeeIPCR}/submit-to-pmt', [DivisionChiefIPCRController::class, 'submitToPMT'])->name('division-chief-employee-ipcr.submitToPMT');
            Route::post('/division-chief-employee-ipcr/{employeeIPCR}/return-from-pmt', [DivisionChiefIPCRController::class, 'returnFromPMT'])->name('division-chief-employee-ipcr.returnFromPMT');
            Route::post('/division-chief-employee-ipcr/submit-to-hr', [DivisionChiefIPCRController::class, 'submitToHR'])->name('division-chief-ipcr.submitToHR');
        });

        // ── Rating Periods / Fiscal Years (requires ipcr.admin) ────────────
        Route::middleware('permission:ipcr.admin')->group(function () {
            Route::get('/performance-management/rating-periods', [\App\Http\Controllers\IPCRRatingPeriodController::class, 'index'])->name('ipcr-rating-periods.index');
            Route::post('/performance-management/rating-periods/fiscal-year', [\App\Http\Controllers\IPCRRatingPeriodController::class, 'storeFiscalYear'])->name('ipcr-rating-periods.storeFiscalYear');
            Route::put('/performance-management/rating-periods/{period}', [\App\Http\Controllers\IPCRRatingPeriodController::class, 'update'])->name('ipcr-rating-periods.update');
            Route::post('/performance-management/rating-periods/{period}/close', [\App\Http\Controllers\IPCRRatingPeriodController::class, 'close'])->name('ipcr-rating-periods.close');
            Route::post('/performance-management/rating-periods/{period}/reopen', [\App\Http\Controllers\IPCRRatingPeriodController::class, 'reopen'])->name('ipcr-rating-periods.reopen');
            Route::post('/performance-management/rating-periods/{period}/set-current', [\App\Http\Controllers\IPCRRatingPeriodController::class, 'setCurrent'])->name('ipcr-rating-periods.setCurrent');
            Route::post('/performance-management/rating-periods/copy-framework', [\App\Http\Controllers\IPCRRatingPeriodController::class, 'copyFramework'])->name('ipcr-rating-periods.copyFramework');
        });

        // ── PMT Review (requires ipcr.approve) ─────────────────────────────
        Route::middleware('permission:ipcr.approve')->group(function () {
            Route::get('/pmt/ipcrs', [PMTIPCRController::class, 'index'])->name('pmt-ipcr.index');
            Route::get('/pmt/ipcrs/{id}', [PMTIPCRController::class, 'show'])->name('pmt-ipcr.show');
            Route::post('/pmt/ipcrs/{employeeIPCR}/approve', [PMTIPCRController::class, 'approve'])->name('pmt-ipcr.approve');
            Route::post('/pmt/ipcrs/{employeeIPCR}/return', [PMTIPCRController::class, 'returnForRevision'])->name('pmt-ipcr.return');
            Route::post('/pmt/ipcrs/{employeeIPCR}/director-sign', [PMTIPCRController::class, 'directorSign'])->name('pmt-ipcr.directorSign');
        });

        // ── HR IPCR Review (requires ipcr.monitor) ─────────────────────────
        Route::middleware('permission:ipcr.monitor')->group(function () {
            Route::get('/hr/ipcrs', [HRIPCRController::class, 'index'])->name('hr-ipcr.index');
            Route::get('/hr/ipcrs/{id}', [HRIPCRController::class, 'show'])->name('hr-ipcr.show');
            Route::post('/hr/ipcrs/{employeeIPCR}/submit-to-pmt', [HRIPCRController::class, 'submitToPMT'])->name('hr-ipcr.submitToPMT');
            Route::post('/hr/ipcrs/batch-submit-to-pmt', [HRIPCRController::class, 'batchSubmitToPMT'])->name('hr-ipcr.batchSubmitToPMT');
        });
    });

    // ── Admin IPCR Monitoring — read-only view across every stage ──────────
    Route::middleware('role:Administrator')->group(function () {
        Route::get('/admin/ipcrs', [\App\Http\Controllers\AdminIPCRController::class, 'index'])->name('admin-ipcr.index');
        Route::get('/admin/ipcrs/{id}', [\App\Http\Controllers\AdminIPCRController::class, 'show'])->name('admin-ipcr.show');
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

    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Employee Digital ID — own card, print layout with verify QR
    Route::get('/profile/id-card', [\App\Http\Controllers\EmployeeIdController::class, 'show'])->name('profile.id-card');

    // Digital Signature — profile setup
    Route::get('/profile/signature', [\App\Http\Controllers\UserSignatureController::class, 'show'])->name('profile.signature');
    Route::post('/profile/signature', [\App\Http\Controllers\UserSignatureController::class, 'saveSignature'])->name('profile.signature.save');
    Route::post('/profile/signature/pin', [\App\Http\Controllers\UserSignatureController::class, 'setPin'])->name('profile.signature.pin');
    Route::post('/profile/signature/verify-pin', [\App\Http\Controllers\UserSignatureController::class, 'verifyPin'])->name('profile.signature.verify-pin');

    /*
    |--------------------------------------------------------------------------
    | Recruitment & Selection Module
    |--------------------------------------------------------------------------
    */
    Route::prefix('recruitment')->name('recruitment.')->group(function () {

        // ── Recruitment Types (admin config) ─────────────────────────────────
        Route::get('/types', [\App\Http\Controllers\Recruitment\RecruitmentTypeController::class, 'index'])
            ->name('types.index');
        Route::put('/types/{recruitmentType}', [\App\Http\Controllers\Recruitment\RecruitmentTypeController::class, 'update'])
            ->name('types.update');
        // Evaluation criteria sub-resource
        Route::post('/types/{recruitmentType}/criteria', [\App\Http\Controllers\Recruitment\RecruitmentTypeController::class, 'storeCriteria'])
            ->name('types.criteria.store');
        Route::put('/types/{recruitmentType}/criteria/{criterion}', [\App\Http\Controllers\Recruitment\RecruitmentTypeController::class, 'updateCriteria'])
            ->name('types.criteria.update');
        Route::delete('/types/{recruitmentType}/criteria/{criterion}', [\App\Http\Controllers\Recruitment\RecruitmentTypeController::class, 'destroyCriteria'])
            ->name('types.criteria.destroy');
        // Onboarding requirements sub-resource
        Route::post('/types/{recruitmentType}/onboarding', [\App\Http\Controllers\Recruitment\RecruitmentTypeController::class, 'storeOnboarding'])
            ->name('types.onboarding.store');
        Route::delete('/types/{recruitmentType}/onboarding/{requirement}', [\App\Http\Controllers\Recruitment\RecruitmentTypeController::class, 'destroyOnboarding'])
            ->name('types.onboarding.destroy');

        // ── Job Items ─────────────────────────────────────────────────────────
        Route::get('/job-items', [\App\Http\Controllers\Recruitment\JobItemController::class, 'index'])
            ->name('job-items.index');
        Route::post('/job-items', [\App\Http\Controllers\Recruitment\JobItemController::class, 'store'])
            ->name('job-items.store');
        Route::put('/job-items/{jobItem}', [\App\Http\Controllers\Recruitment\JobItemController::class, 'update'])
            ->name('job-items.update');
        Route::patch('/job-items/{jobItem}/status', [\App\Http\Controllers\Recruitment\JobItemController::class, 'changeStatus'])
            ->name('job-items.status');
        Route::post('/job-items/{jobItem}/publish', [\App\Http\Controllers\Recruitment\JobItemController::class, 'publish'])
            ->name('job-items.publish');
        Route::delete('/job-items/{jobItem}', [\App\Http\Controllers\Recruitment\JobItemController::class, 'destroy'])
            ->name('job-items.destroy');
        Route::get('/job-items/{jobItem}/art-card/{type}', [\App\Http\Controllers\Recruitment\JobItemController::class, 'downloadArtCard'])
            ->name('job-items.art-card.download');
        Route::post('/job-items/{jobItem}/art-card/regenerate', [\App\Http\Controllers\Recruitment\JobItemController::class, 'regenerateArtCard'])
            ->name('job-items.art-card.regenerate');

        // ── Applicants ────────────────────────────────────────────────────────
        Route::get('/applicants', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'index'])
            ->name('applicants.index');
        Route::get('/applicants/{applicant}', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'show'])
            ->name('applicants.show');
        Route::post('/applicants', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'store'])
            ->name('applicants.store');
        Route::put('/applicants/{applicant}', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'update'])
            ->name('applicants.update');
        Route::delete('/applicants/{applicant}', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'destroy'])
            ->name('applicants.destroy');
        // Documents
        Route::post('/applicants/{applicant}/documents', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'uploadDocument'])
            ->name('applicants.documents.upload');
        Route::patch('/applicants/{applicant}/documents/{document}/verify', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'verifyDocument'])
            ->name('applicants.documents.verify');
        Route::delete('/applicants/{applicant}/documents/{document}', [\App\Http\Controllers\Recruitment\ApplicantController::class, 'destroyDocument'])
            ->name('applicants.documents.destroy');

        // ── Applications ──────────────────────────────────────────────────────
        Route::get('/applications', [\App\Http\Controllers\Recruitment\ApplicationController::class, 'index'])
            ->name('applications.index');
        Route::get('/applications/{application}', [\App\Http\Controllers\Recruitment\ApplicationController::class, 'show'])
            ->name('applications.show');
        Route::post('/applications', [\App\Http\Controllers\Recruitment\ApplicationController::class, 'store'])
            ->name('applications.store');
        Route::patch('/applications/{application}/advance', [\App\Http\Controllers\Recruitment\ApplicationController::class, 'advance'])
            ->name('applications.advance');
        Route::patch('/applications/{application}/reject', [\App\Http\Controllers\Recruitment\ApplicationController::class, 'reject'])
            ->name('applications.reject');
        Route::patch('/applications/{application}/withdraw', [\App\Http\Controllers\Recruitment\ApplicationController::class, 'withdraw'])
            ->name('applications.withdraw');

        // ── Evaluation ────────────────────────────────────────────────────────
        Route::post('/applications/{application}/scores', [\App\Http\Controllers\Recruitment\EvaluationController::class, 'saveScores'])
            ->name('evaluations.scores');
        Route::post('/applications/{application}/interview', [\App\Http\Controllers\Recruitment\EvaluationController::class, 'scheduleInterview'])
            ->name('evaluations.interview.schedule');
        Route::patch('/applications/{application}/interview/{interview}/result', [\App\Http\Controllers\Recruitment\EvaluationController::class, 'recordInterviewResult'])
            ->name('evaluations.interview.result');
        Route::post('/applications/{application}/rank', [\App\Http\Controllers\Recruitment\EvaluationController::class, 'computeRanking'])
            ->name('evaluations.rank');
        Route::post('/vacancies/{jobVacancyId}/re-rank', [\App\Http\Controllers\Recruitment\EvaluationController::class, 'reRankVacancy'])
            ->name('evaluations.rerank');
        Route::patch('/applications/{application}/recommend', [\App\Http\Controllers\Recruitment\EvaluationController::class, 'recommend'])
            ->name('evaluations.recommend');

        // ── Placements & Onboarding ───────────────────────────────────────────
        Route::get('/placements', [\App\Http\Controllers\Recruitment\PlacementController::class, 'index'])
            ->name('placements.index');
        Route::get('/placements/{placement}', [\App\Http\Controllers\Recruitment\PlacementController::class, 'show'])
            ->name('placements.show');
        Route::post('/applications/{application}/approve', [\App\Http\Controllers\Recruitment\PlacementController::class, 'approve'])
            ->name('placements.approve');
        Route::post('/applications/{application}/disapprove', [\App\Http\Controllers\Recruitment\PlacementController::class, 'disapprove'])
            ->name('placements.disapprove');
        Route::patch('/placements/{placement}/tasks/{task}/complete', [\App\Http\Controllers\Recruitment\PlacementController::class, 'completeTask'])
            ->name('placements.tasks.complete');
        Route::patch('/placements/{placement}/tasks/{task}/skip', [\App\Http\Controllers\Recruitment\PlacementController::class, 'skipTask'])
            ->name('placements.tasks.skip');
        Route::patch('/placements/{placement}/tasks/{task}/assign', [\App\Http\Controllers\Recruitment\PlacementController::class, 'assignTask'])
            ->name('placements.tasks.assign');

        // ── Reports ───────────────────────────────────────────────────────────
        Route::get('/reports', [\App\Http\Controllers\Recruitment\RecruitmentReportController::class, 'index'])
            ->name('reports.index');

        // ── HRMPSB Member Management ──────────────────────────────────────────
        Route::get('/hrmpsb', [\App\Http\Controllers\Recruitment\HrmpsbController::class, 'index'])
            ->name('hrmpsb.index');
        Route::post('/hrmpsb/assign', [\App\Http\Controllers\Recruitment\HrmpsbController::class, 'assign'])
            ->name('hrmpsb.assign');
        Route::delete('/hrmpsb/{user}', [\App\Http\Controllers\Recruitment\HrmpsbController::class, 'remove'])
            ->name('hrmpsb.remove');
    });
});

/*
|--------------------------------------------------------------------------
| Recruitment — Public Job Portal (no auth required)
|--------------------------------------------------------------------------
*/
Route::prefix('jobs')->name('recruitment.public.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Recruitment\PublicVacancyController::class, 'index'])
        ->name('vacancies.index');
    Route::get('/track', [\App\Http\Controllers\Recruitment\PublicVacancyController::class, 'trackForm'])
        ->name('track');
    Route::post('/track', [\App\Http\Controllers\Recruitment\PublicVacancyController::class, 'track'])
        ->name('track.submit');
    Route::get('/{vacancy}', [\App\Http\Controllers\Recruitment\PublicVacancyController::class, 'show'])
        ->name('vacancies.show');
    Route::post('/{vacancy}/apply', [\App\Http\Controllers\Recruitment\PublicVacancyController::class, 'apply'])
        ->name('vacancies.apply');
});
/*
|--------------------------------------------------------------------------
| Salary Grade Module
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:recruitment.view'])->group(function () {
    Route::get('/salary-grades', [\App\Http\Controllers\SalaryGradeController::class, 'index'])
        ->name('salary-grades.index');
    Route::put('/salary-grades/{salaryGrade}', [\App\Http\Controllers\SalaryGradeController::class, 'update'])
        ->name('salary-grades.update');
    Route::post('/salary-grades/tranche', [\App\Http\Controllers\SalaryGradeController::class, 'storeTranche'])
        ->name('salary-grades.tranche.store');
    Route::post('/salary-grades/tranche/activate', [\App\Http\Controllers\SalaryGradeController::class, 'setCurrentTranche'])
        ->name('salary-grades.tranche.activate');
});

/*
|--------------------------------------------------------------------------
| Learning & Development (L&D) Module
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('lnd')->name('lnd.')->group(function () {

    // ── Learning Programs ─────────────────────────────────────────────────────
    Route::get('/programs', [\App\Http\Controllers\LnD\LearningProgramController::class, 'index'])
        ->name('programs.index');
    Route::post('/programs', [\App\Http\Controllers\LnD\LearningProgramController::class, 'store'])
        ->name('programs.store');
    Route::get('/programs/{learningProgram}', [\App\Http\Controllers\LnD\LearningProgramController::class, 'show'])
        ->name('programs.show');
    Route::put('/programs/{learningProgram}', [\App\Http\Controllers\LnD\LearningProgramController::class, 'update'])
        ->name('programs.update');
    Route::delete('/programs/{learningProgram}', [\App\Http\Controllers\LnD\LearningProgramController::class, 'destroy'])
        ->name('programs.destroy');
    Route::patch('/programs/{learningProgram}/status', [\App\Http\Controllers\LnD\LearningProgramController::class, 'updateStatus'])
        ->name('programs.status');

    // ── Training Sessions ─────────────────────────────────────────────────────
    Route::get('/sessions', [\App\Http\Controllers\LnD\TrainingSessionController::class, 'index'])
        ->name('sessions.index');
    Route::post('/sessions', [\App\Http\Controllers\LnD\TrainingSessionController::class, 'store'])
        ->name('sessions.store');
    Route::get('/sessions/{trainingSession}', [\App\Http\Controllers\LnD\TrainingSessionController::class, 'show'])
        ->name('sessions.show');
    Route::put('/sessions/{trainingSession}', [\App\Http\Controllers\LnD\TrainingSessionController::class, 'update'])
        ->name('sessions.update');
    Route::delete('/sessions/{trainingSession}', [\App\Http\Controllers\LnD\TrainingSessionController::class, 'destroy'])
        ->name('sessions.destroy');
    Route::post('/sessions/{trainingSession}/attendance', [\App\Http\Controllers\LnD\TrainingSessionController::class, 'markAttendance'])
        ->name('sessions.attendance');
    Route::post('/sessions/{trainingSession}/complete', [\App\Http\Controllers\LnD\TrainingSessionController::class, 'complete'])
        ->name('sessions.complete');
    Route::get('/sessions/{trainingSession}/evaluation-summary', [\App\Http\Controllers\LnD\TrainingEvaluationController::class, 'sessionSummary'])
        ->name('sessions.evaluation-summary');

    // ── Training Participants ─────────────────────────────────────────────────
    Route::post('/sessions/{trainingSession}/participants', [\App\Http\Controllers\LnD\TrainingParticipantController::class, 'store'])
        ->name('participants.store');
    Route::delete('/participants/{trainingParticipant}', [\App\Http\Controllers\LnD\TrainingParticipantController::class, 'destroy'])
        ->name('participants.destroy');
    Route::patch('/participants/{trainingParticipant}/nomination', [\App\Http\Controllers\LnD\TrainingParticipantController::class, 'approveNomination'])
        ->name('participants.nomination');
    Route::post('/participants/{trainingParticipant}/certificate', [\App\Http\Controllers\LnD\TrainingParticipantController::class, 'uploadCertificate'])
        ->name('participants.certificate');

    // ── Employee Self-Service ─────────────────────────────────────────────────
    Route::get('/my-trainings', [\App\Http\Controllers\LnD\TrainingParticipantController::class, 'myTrainings'])
        ->name('my-trainings');
    Route::get('/my-idp', [\App\Http\Controllers\LnD\IDPController::class, 'myIdp'])
        ->name('my-idp');
    Route::post('/my-tna', [\App\Http\Controllers\LnD\TrainingNeedsController::class, 'submitOwn'])
        ->name('my-tna.store');

    // ── Training Needs (TNA) ──────────────────────────────────────────────────
    Route::get('/tna', [\App\Http\Controllers\LnD\TrainingNeedsController::class, 'index'])
        ->name('tna.index');
    Route::post('/tna', [\App\Http\Controllers\LnD\TrainingNeedsController::class, 'store'])
        ->name('tna.store');
    Route::put('/tna/{trainingNeed}', [\App\Http\Controllers\LnD\TrainingNeedsController::class, 'update'])
        ->name('tna.update');
    Route::delete('/tna/{trainingNeed}', [\App\Http\Controllers\LnD\TrainingNeedsController::class, 'destroy'])
        ->name('tna.destroy');
    Route::patch('/tna/{trainingNeed}/approve', [\App\Http\Controllers\LnD\TrainingNeedsController::class, 'approve'])
        ->name('tna.approve');
    Route::get('/tna/consolidation', [\App\Http\Controllers\LnD\TrainingNeedsController::class, 'consolidation'])
        ->name('tna.consolidation');

    // ── Individual Development Plans (IDP) ────────────────────────────────────
    Route::get('/idp', [\App\Http\Controllers\LnD\IDPController::class, 'index'])
        ->name('idp.index');
    Route::post('/idp', [\App\Http\Controllers\LnD\IDPController::class, 'store'])
        ->name('idp.store');
    Route::get('/idp/{iDP}', [\App\Http\Controllers\LnD\IDPController::class, 'show'])
        ->name('idp.show');
    Route::put('/idp/{iDP}', [\App\Http\Controllers\LnD\IDPController::class, 'update'])
        ->name('idp.update');
    Route::delete('/idp/{iDP}', [\App\Http\Controllers\LnD\IDPController::class, 'destroy'])
        ->name('idp.destroy');
    Route::patch('/idp/{iDP}/submit', [\App\Http\Controllers\LnD\IDPController::class, 'submit'])
        ->name('idp.submit');
    Route::patch('/idp/{iDP}/approve', [\App\Http\Controllers\LnD\IDPController::class, 'approve'])
        ->name('idp.approve');
    Route::patch('/idp/{iDP}/status', [\App\Http\Controllers\LnD\IDPController::class, 'updateStatus'])
        ->name('idp.status');
    Route::get('/team-idp', [\App\Http\Controllers\LnD\IDPController::class, 'teamIdp'])
        ->name('team-idp');

    // ── Kirkpatrick Evaluations ───────────────────────────────────────────────
    Route::get('/participants/{trainingParticipant}/evaluation', [\App\Http\Controllers\LnD\TrainingEvaluationController::class, 'show'])
        ->name('evaluations.show');
    Route::post('/participants/{trainingParticipant}/evaluation/reaction', [\App\Http\Controllers\LnD\TrainingEvaluationController::class, 'storeReaction'])
        ->name('evaluations.reaction');
    Route::post('/participants/{trainingParticipant}/evaluation/learning', [\App\Http\Controllers\LnD\TrainingEvaluationController::class, 'storeLearning'])
        ->name('evaluations.learning');
    Route::post('/participants/{trainingParticipant}/evaluation/behavior', [\App\Http\Controllers\LnD\TrainingEvaluationController::class, 'storeBehavior'])
        ->name('evaluations.behavior');
    Route::post('/participants/{trainingParticipant}/evaluation/results', [\App\Http\Controllers\LnD\TrainingEvaluationController::class, 'storeResults'])
        ->name('evaluations.results');
    Route::patch('/participants/{trainingParticipant}/evaluation/feedback', [\App\Http\Controllers\LnD\TrainingEvaluationController::class, 'updateFeedback'])
        ->name('evaluations.feedback');
});

/*
|--------------------------------------------------------------------------
| Rewards & Recognition Module (PRAISE)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('rewards')->name('rewards.')->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', [\App\Http\Controllers\Rewards\RecognitionReportController::class, 'dashboard'])
        ->name('dashboard');

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::get('/reports', [\App\Http\Controllers\Rewards\RecognitionReportController::class, 'report'])
        ->name('reports');

    // ── Reward Types ──────────────────────────────────────────────────────────
    Route::get('/types', [\App\Http\Controllers\Rewards\RewardTypeController::class, 'index'])
        ->name('types.index');
    Route::post('/types', [\App\Http\Controllers\Rewards\RewardTypeController::class, 'store'])
        ->name('types.store');
    Route::patch('/types/{rewardType}', [\App\Http\Controllers\Rewards\RewardTypeController::class, 'update'])
        ->name('types.update');
    Route::delete('/types/{rewardType}', [\App\Http\Controllers\Rewards\RewardTypeController::class, 'destroy'])
        ->name('types.destroy');

    // ── Nominations ───────────────────────────────────────────────────────────
    Route::get('/nominations', [\App\Http\Controllers\Rewards\RewardNominationController::class, 'index'])
        ->name('nominations.index');
    Route::get('/nominations/create', [\App\Http\Controllers\Rewards\RewardNominationController::class, 'create'])
        ->name('nominations.create');
    Route::post('/nominations', [\App\Http\Controllers\Rewards\RewardNominationController::class, 'store'])
        ->name('nominations.store');
    Route::get('/nominations/{rewardNomination}', [\App\Http\Controllers\Rewards\RewardNominationController::class, 'show'])
        ->name('nominations.show');
    Route::post('/nominations/{rewardNomination}/screen', [\App\Http\Controllers\Rewards\RewardNominationController::class, 'screen'])
        ->name('nominations.screen');
    Route::delete('/nominations/{rewardNomination}', [\App\Http\Controllers\Rewards\RewardNominationController::class, 'destroy'])
        ->name('nominations.destroy');

    // ── My Recognitions ───────────────────────────────────────────────────────
    Route::get('/my-recognitions', [\App\Http\Controllers\Rewards\RewardNominationController::class, 'myNominations'])
        ->name('my-recognitions');

    // ── Evaluation Panel ──────────────────────────────────────────────────────
    Route::get('/evaluations', [\App\Http\Controllers\Rewards\RewardEvaluationController::class, 'panel'])
        ->name('evaluations.panel');
    Route::post('/nominations/{rewardNomination}/evaluations', [\App\Http\Controllers\Rewards\RewardEvaluationController::class, 'store'])
        ->name('evaluations.store');
    Route::patch('/evaluations/{rewardEvaluation}', [\App\Http\Controllers\Rewards\RewardEvaluationController::class, 'update'])
        ->name('evaluations.update');

    // ── Approvals ─────────────────────────────────────────────────────────────
    Route::get('/approvals', [\App\Http\Controllers\Rewards\RewardApprovalController::class, 'index'])
        ->name('approvals.index');
    Route::post('/nominations/{rewardNomination}/approve', [\App\Http\Controllers\Rewards\RewardApprovalController::class, 'decide'])
        ->name('approvals.decide');

    // ── Awards (recording actual incentives) ──────────────────────────────────
    Route::get('/awards', [\App\Http\Controllers\Rewards\RewardController::class, 'index'])
        ->name('awards.index');
    Route::post('/nominations/{rewardNomination}/award', [\App\Http\Controllers\Rewards\RewardController::class, 'store'])
        ->name('awards.store');
    Route::patch('/awards/{reward}', [\App\Http\Controllers\Rewards\RewardController::class, 'update'])
        ->name('awards.update');
});

// ══════════════════════════════════════════════════════════════════════════════
// HR Module Routes
// ══════════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'verified'])->prefix('hr')->name('hr.')->group(function () {

    // ── Comprehensive HR Dashboard ────────────────────────────────────────────
    Route::get('/dashboard', [\App\Http\Controllers\HR\HRDashboardController::class, 'index'])
        ->middleware('permission:hr.dashboard.view')
        ->name('dashboard');

    // ── Employee Profiles ─────────────────────────────────────────────────────
    Route::get('/employees/{user}/profile', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'show'])
        ->name('employees.profile.show');
    Route::put('/employees/{user}/profile', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'update'])
        ->name('employees.profile.update');

    // ── Service Records ──────────────────────────────────────────────────────
    Route::get('/service-records', [\App\Http\Controllers\HR\ServiceRecordController::class, 'index'])
        ->name('service-records.index');
    Route::get('/service-records/my', [\App\Http\Controllers\HR\ServiceRecordController::class, 'my'])
        ->name('service-records.my');
    Route::get('/service-records/evidence/{evidence}', [\App\Http\Controllers\HR\ServiceRecordController::class, 'evidence'])
        ->name('service-records.evidence.show');
    Route::delete('/service-records/evidence/{evidence}', [\App\Http\Controllers\HR\ServiceRecordController::class, 'destroyEvidence'])
        ->name('service-records.evidence.destroy');
    Route::post('/service-records/entries/{entry}/evidence', [\App\Http\Controllers\HR\ServiceRecordController::class, 'storeEvidence'])
        ->name('service-records.evidence.store');
    Route::post('/service-records/entries/{entry}/verify', [\App\Http\Controllers\HR\ServiceRecordController::class, 'verify'])
        ->name('service-records.verify');
    Route::post('/service-records/entries/{entry}/supersede', [\App\Http\Controllers\HR\ServiceRecordController::class, 'supersede'])
        ->name('service-records.supersede');
    Route::delete('/service-records/actions/{action}', [\App\Http\Controllers\HR\ServiceRecordController::class, 'destroyAction'])
        ->name('service-records.actions.destroy');
    Route::get('/service-records/{user}/export.csv', [\App\Http\Controllers\HR\ServiceRecordController::class, 'export'])
        ->name('service-records.export');
    Route::get('/service-records/{user}', [\App\Http\Controllers\HR\ServiceRecordController::class, 'show'])
        ->name('service-records.show');
    Route::post('/service-records/{user}/entries', [\App\Http\Controllers\HR\ServiceRecordController::class, 'store'])
        ->name('service-records.store');
    Route::put('/service-records/{user}/entries/{entry}', [\App\Http\Controllers\HR\ServiceRecordController::class, 'update'])
        ->name('service-records.update');
    Route::post('/service-records/{user}/import-pds', [\App\Http\Controllers\HR\ServiceRecordController::class, 'importPds'])
        ->name('service-records.import-pds');
    Route::post('/service-records/{user}/actions', [\App\Http\Controllers\HR\ServiceRecordController::class, 'storeAction'])
        ->name('service-records.actions.store');

    // ── Leave Applications ────────────────────────────────────────────────────
    Route::get('/leave', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'index'])
        ->name('leave.index');
    Route::get('/leave/create', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'create'])
        ->name('leave.create');
    Route::get('/leave/balance/check', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'checkBalance'])
        ->name('leave.balance.check');
    Route::get('/leave-credits/my', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'myCredits'])
        ->name('leave-credits.my');
    Route::post('/leave-credits/my/service-credits', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'myServiceCreditsStore'])
        ->name('leave-credits.my.service-credits.store');
    Route::post('/leave', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'store'])
        ->name('leave.store');
    Route::get('/leave/{leaveApplication}', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'show'])
        ->name('leave.show');
    Route::post('/leave/{leaveApplication}/approve', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'approve'])
        ->name('leave.approve');
    Route::post('/leave/{leaveApplication}/cancel', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'cancel'])
        ->name('leave.cancel');
    Route::get('/leave/{leaveApplication}/print', [\App\Http\Controllers\HR\LeaveApplicationController::class, 'printForm'])
        ->name('leave.print');

    // ── Legacy Leave Import (HR only) ────────────────────────────────────────
    Route::get('/leave/legacy-import', [\App\Http\Controllers\HR\LegacyLeaveImportController::class, 'index'])
        ->name('leave.legacy-import.index');
    Route::post('/leave/legacy-import', [\App\Http\Controllers\HR\LegacyLeaveImportController::class, 'store'])
        ->name('leave.legacy-import.store');

    // ── Leave Credit Administration (HR only) ─────────────────────────────────
    Route::get('/leave-credits/initialize', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'initializeIndex'])
        ->name('leave-credits.initialize');
    Route::post('/leave-credits/initialize', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'initializeStore'])
        ->name('leave-credits.initialize.store');

    Route::get('/leave-credits/adjust', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'adjustIndex'])
        ->name('leave-credits.adjust');
    Route::post('/leave-credits/adjust', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'adjustStore'])
        ->name('leave-credits.adjust.store');

    Route::get('/leave-credits/service-credits', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'serviceCreditsIndex'])
        ->name('leave-credits.service-credits');
    Route::post('/leave-credits/service-credits/{record}/approve', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'serviceCreditsApprove'])
        ->name('leave-credits.service-credits.approve');
    Route::post('/leave-credits/service-credits/{record}/reject', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'serviceCreditsReject'])
        ->name('leave-credits.service-credits.reject');

    Route::get('/leave-credits/ledger/{user}', [\App\Http\Controllers\HR\LeaveCreditAdminController::class, 'ledger'])
        ->name('leave-credits.ledger');

    // ── Leave Credit Reports ───────────────────────────────────────────────────
    Route::get('/reports/leave-credits/ledger', [\App\Http\Controllers\HR\LeaveCreditReportController::class, 'ledger'])
        ->name('reports.leave-credits.ledger');
    Route::get('/reports/leave-credits/accrual', [\App\Http\Controllers\HR\LeaveCreditReportController::class, 'accrual'])
        ->name('reports.leave-credits.accrual');
    Route::get('/reports/leave-credits/utilization', [\App\Http\Controllers\HR\LeaveCreditReportController::class, 'utilization'])
        ->name('reports.leave-credits.utilization');

    // ── Holidays ──────────────────────────────────────────────────────────────
    Route::get('/holidays', [\App\Http\Controllers\HR\HolidayController::class, 'index'])
        ->name('holidays.index');
    Route::post('/holidays', [\App\Http\Controllers\HR\HolidayController::class, 'store'])
        ->name('holidays.store');
    Route::put('/holidays/{holiday}', [\App\Http\Controllers\HR\HolidayController::class, 'update'])
        ->name('holidays.update');
    Route::delete('/holidays/{holiday}', [\App\Http\Controllers\HR\HolidayController::class, 'destroy'])
        ->name('holidays.destroy');

    // ── Biometric Logs ────────────────────────────────────────────────────────
    Route::get('/biometric', [\App\Http\Controllers\HR\BiometricLogController::class, 'index'])
        ->name('biometric.index');
    Route::post('/biometric/upload', [\App\Http\Controllers\HR\BiometricLogController::class, 'upload'])
        ->name('biometric.upload');
    Route::post('/biometric/{log}/resolve', [\App\Http\Controllers\HR\BiometricLogController::class, 'resolve'])
        ->name('biometric.resolve');

    // ── My DTR (employee self-service) ───────────────────────────────────────
    Route::get('/my-dtr', [\App\Http\Controllers\HR\DtrRecordController::class, 'myDtr'])
        ->name('my-dtr.index');
    Route::get('/my-dtr/checklist', [\App\Http\Controllers\HR\DtrRecordController::class, 'myDtrChecklist'])
        ->name('my-dtr.checklist');
    Route::patch('/my-dtr/{record}/penned', [\App\Http\Controllers\HR\DtrRecordController::class, 'myPenned'])
        ->name('my-dtr.penned');
    Route::post('/my-dtr/submit-penned', [\App\Http\Controllers\HR\DtrRecordController::class, 'submitPenned'])
        ->name('my-dtr.submit-penned');
    Route::post('/my-dtr/generate-advance', [\App\Http\Controllers\HR\DtrRecordController::class, 'myGenerateAdvance'])
        ->name('my-dtr.generate-advance');

    // ── DTR Records ───────────────────────────────────────────────────────────
    Route::get('/dtr', [\App\Http\Controllers\HR\DtrRecordController::class, 'index'])
        ->name('dtr.index');
    Route::get('/dtr/{user}/show', [\App\Http\Controllers\HR\DtrRecordController::class, 'show'])
        ->name('dtr.show');
    Route::post('/dtr/generate', [\App\Http\Controllers\HR\DtrRecordController::class, 'generate'])
        ->name('dtr.generate');
    Route::patch('/dtr/{record}/edit', [\App\Http\Controllers\HR\DtrRecordController::class, 'edit'])
        ->name('dtr.edit');
    Route::patch('/dtr/{record}/lock', [\App\Http\Controllers\HR\DtrRecordController::class, 'lock'])
        ->name('dtr.lock');
    Route::post('/dtr/{user}/recompute', [\App\Http\Controllers\HR\DtrRecordController::class, 'recompute'])
        ->name('dtr.recompute');
    Route::post('/dtr/{user}/unlock-penned', [\App\Http\Controllers\HR\DtrRecordController::class, 'unlockPenned'])
        ->name('dtr.unlock-penned');
    Route::patch('/dtr/{record}/penned', [\App\Http\Controllers\HR\DtrRecordController::class, 'penned'])
        ->name('dtr.penned');
    Route::get('/dtr/print-batch', [\App\Http\Controllers\HR\DtrRecordController::class, 'printBatch'])
        ->name('dtr.print.batch');
    Route::get('/dtr/{user}/print', [\App\Http\Controllers\HR\DtrRecordController::class, 'printCsc'])
        ->name('dtr.print');
    Route::get('/dtr/{user}/checklist', [\App\Http\Controllers\HR\DtrRecordController::class, 'printChecklist'])
        ->name('dtr.checklist');

    // ── Work Schedules — HR Admin ─────────────────────────────────────────────
    Route::get('/schedules', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'index'])
        ->name('schedules.index');
    Route::post('/schedules/presets', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'storePreset'])
        ->name('schedules.presets.store');
    Route::put('/schedules/presets/{preset}', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'updatePreset'])
        ->name('schedules.presets.update');
    Route::delete('/schedules/presets/{preset}', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'destroyPreset'])
        ->name('schedules.presets.destroy');
    Route::post('/schedules/assign', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'assign'])
        ->name('schedules.assign');
    Route::post('/schedules/{schedule}/approve', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'approveSubmission'])
        ->name('schedules.approve');
    Route::post('/schedules/{schedule}/reject', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'rejectSubmission'])
        ->name('schedules.reject');

    // ── Work Schedules — Employee Self-Service ────────────────────────────────
    Route::get('/schedules/my', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'mySchedule'])
        ->name('schedules.my');
    Route::post('/schedules/submit', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'submit'])
        ->name('schedules.submit');
    Route::delete('/schedules/{schedule}/cancel', [\App\Http\Controllers\HR\EmployeeScheduleController::class, 'cancelSubmission'])
        ->name('schedules.cancel');

    // ── Employee 201 Files ──────────────────────────────────────────────────────
    Route::get('/201-files', [EmployeeDocumentController::class, 'listEmployees'])
        ->name('twoohone.index');
    Route::get('/employees/{user}/documents', [EmployeeDocumentController::class, 'index'])
        ->name('employees.documents.index');
    Route::post('/employees/{user}/documents', [EmployeeDocumentController::class, 'store'])
        ->name('employees.documents.store');
    Route::delete('/documents/{employeeDocument}', [EmployeeDocumentController::class, 'destroy'])
        ->name('employees.documents.destroy');
    Route::get('/documents/{employeeDocument}/download', [EmployeeDocumentController::class, 'download'])
        ->name('employees.documents.download');

    // ── HR Document Requests ──────────────────────────────────────────────────
    Route::get('/document-requests', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'index'])
        ->name('document-requests.index');
    Route::post('/document-requests', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'store'])
        ->name('document-requests.store');
    Route::get('/document-requests/manage', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'manage'])
        ->name('document-requests.manage');
    Route::get('/document-requests/issued/{issuedDocument}/download', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'download'])
        ->name('document-requests.download');
    Route::get('/document-requests/attachments/{attachment}', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'attachment'])
        ->name('document-requests.attachments.download');
    Route::get('/document-requests/{documentRequest}', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'show'])
        ->name('document-requests.show');
    Route::post('/document-requests/{documentRequest}/cancel', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'cancel'])
        ->name('document-requests.cancel');
    Route::post('/document-requests/{documentRequest}/resubmit', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'resubmit'])
        ->name('document-requests.resubmit');
    Route::post('/document-requests/{documentRequest}/start', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'start'])
        ->name('document-requests.start');
    Route::post('/document-requests/{documentRequest}/prepare', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'prepare'])
        ->name('document-requests.prepare');
    Route::post('/document-requests/{documentRequest}/return', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'returnToEmployee'])
        ->name('document-requests.return');
    Route::post('/document-requests/{documentRequest}/reject', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'reject'])
        ->name('document-requests.reject');
    Route::post('/document-requests/{documentRequest}/route-to-ocd', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'routeToOcd'])
        ->name('document-requests.route-to-ocd');
    Route::post('/document-requests/{documentRequest}/issue', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'issue'])
        ->name('document-requests.issue');
    Route::put('/document-request-types/{requestType}', [\App\Http\Controllers\HR\HrDocumentRequestController::class, 'updateType'])
        ->name('document-request-types.update');
});

// ══════════════════════════════════════════════════════════════════════════════
// Payroll Module Routes
// ══════════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'verified'])->prefix('payroll')->name('payroll.')->group(function () {

    Route::get('/', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'index'])
        ->name('index');
    Route::get('/create', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'create'])
        ->name('create');
    Route::post('/', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'store'])
        ->name('store');
    // Allowance Types management (must be before /{payrollRun} wildcard)
    Route::get('/allowances', [\App\Http\Controllers\HR\AllowanceTypeController::class, 'index'])
        ->name('allowances.index');
    Route::post('/allowances', [\App\Http\Controllers\HR\AllowanceTypeController::class, 'store'])
        ->name('allowances.store');
    Route::put('/allowances/{allowanceType}', [\App\Http\Controllers\HR\AllowanceTypeController::class, 'update'])
        ->name('allowances.update');
    Route::patch('/allowances/{allowanceType}/toggle', [\App\Http\Controllers\HR\AllowanceTypeController::class, 'toggle'])
        ->name('allowances.toggle');

    // ── Cashier Payroll Upload (must be before /{payrollRun} wildcard) ─────────
    Route::middleware('permission:payroll.upload|payroll.view_all')->prefix('cashier')->name('cashier.')->group(function () {
        Route::get('/',                                   [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'index'])->name('index');
        Route::get('/upload',                             [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'uploadForm'])->name('upload');
        Route::post('/upload',                            [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'upload'])->name('upload.store');
        Route::get('/csv-template',                       [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'csvTemplate'])->name('csv-template');
        Route::get('/{batch}/preview',                    [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'preview'])->name('preview');
        Route::post('/{batch}/resolve',                   [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'resolve'])->name('resolve');
        Route::post('/{batch}/adjustments',               [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'adjustments'])->name('adjustments');
        Route::post('/{batch}/send',                      [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'send'])->name('send');
        Route::post('/{batch}/send-second-half',          [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'sendSecondHalf'])->name('send-second-half');
        Route::get('/{batch}/status',                     [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'status'])->name('status');
        Route::post('/{batch}/resend',                    [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'resend'])->name('resend');
        Route::get('/{batch}/log.csv',                    [\App\Http\Controllers\Payroll\PayrollCashierController::class, 'auditCsv'])->name('audit-csv');
    });

    // ── Employee Payslip History (must be before /{payrollRun} wildcard) ───────
    Route::prefix('my-payslips')->name('my-payslips.')->group(function () {
        Route::get('/',              [\App\Http\Controllers\Payroll\PayrollEmployeeController::class, 'index'])->name('index');
        Route::get('/{payrollItem}', [\App\Http\Controllers\Payroll\PayrollEmployeeController::class, 'show'])->name('show');
        Route::get('/{payrollItem}/pdf', [\App\Http\Controllers\Payroll\PayrollEmployeeController::class, 'pdf'])->name('pdf');
    });

    Route::get('/{payrollRun}', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'show'])
        ->name('show');
    Route::post('/{payrollRun}/process', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'process'])
        ->name('process');
    Route::post('/{payrollRun}/approve', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'approve'])
        ->name('approve');
    Route::post('/{payrollRun}/release', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'release'])
        ->name('release');
    Route::post('/{payrollRun}/cancel', [\App\Http\Controllers\Payroll\PayrollRunController::class, 'cancel'])
        ->name('cancel');

    // Payslip & Reports
    Route::get('/{payrollRun}/payslip/{payrollRecord}', [\App\Http\Controllers\Payroll\PayslipController::class, 'download'])
        ->name('payslip.download');
    Route::post('/{payrollRun}/payslips/zip', [\App\Http\Controllers\Payroll\PayslipController::class, 'downloadAll'])
        ->name('payslips.zip');
    Route::get('/{payrollRun}/reports/register', [\App\Http\Controllers\Payroll\PayslipController::class, 'payrollRegister'])
        ->name('reports.register');
    Route::get('/{payrollRun}/reports/deductions', [\App\Http\Controllers\Payroll\PayslipController::class, 'deductionsRegister'])
        ->name('reports.deductions');
});

// HR Reports (outside payroll prefix)
Route::middleware(['auth', 'verified'])->prefix('hr')->name('hr.')->group(function () {
    Route::get('/reports/dtr', [\App\Http\Controllers\Payroll\PayslipController::class, 'dtrSummary'])
        ->name('reports.dtr');
});

// ── Organizational Structure Module ───────────────────────────────────────────
Route::middleware(['auth', 'verified'])->prefix('hr/org')->name('hr.org.')->group(function () {

    // ── Chart / tree views (read — any authenticated user with org.view) ────────
    Route::get('/', [\App\Http\Controllers\HR\OrgUnitController::class, 'index'])
        ->name('index');
    Route::get('/tree', [\App\Http\Controllers\HR\OrgUnitController::class, 'tree'])
        ->name('tree');                          // JSON — used by Vue org-chart
    Route::get('/units', [\App\Http\Controllers\HR\OrgUnitController::class, 'list'])
        ->name('units.list');                    // JSON — flat paginated list
    Route::get('/units/{unit}', [\App\Http\Controllers\HR\OrgUnitController::class, 'show'])
        ->name('units.show');                    // Inertia page

    // ── Unit CRUD (require org.units.* permissions) ─────────────────────────────
    Route::post('/units', [\App\Http\Controllers\HR\OrgUnitController::class, 'store'])
        ->name('units.store');
    Route::put('/units/{unit}', [\App\Http\Controllers\HR\OrgUnitController::class, 'update'])
        ->name('units.update');
    Route::patch('/units/{unit}/move', [\App\Http\Controllers\HR\OrgUnitController::class, 'move'])
        ->name('units.move');
    Route::delete('/units/{unit}', [\App\Http\Controllers\HR\OrgUnitController::class, 'destroy'])
        ->name('units.destroy');
    Route::post('/units/{unit}/restore', [\App\Http\Controllers\HR\OrgUnitController::class, 'restore'])
        ->name('units.restore');

    // ── Employee assignments ────────────────────────────────────────────────────
    Route::get('/units/{unit}/assignments', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'index'])
        ->name('assignments.index');
    Route::get('/employees/{user}/assignments', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'forEmployee'])
        ->name('assignments.for-employee');
    Route::post('/assignments', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'store'])
        ->name('assignments.store');
    Route::put('/assignments/{assignment}', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'update'])
        ->name('assignments.update');
    Route::patch('/assignments/{assignment}/end', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'end'])
        ->name('assignments.end');
    Route::delete('/assignments/{assignment}', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'destroy'])
        ->name('assignments.destroy');

    // ── Unit heads ──────────────────────────────────────────────────────────────
    Route::get('/heads', [\App\Http\Controllers\HR\UnitHeadController::class, 'allCurrent'])
        ->name('heads.all');
    Route::get('/units/{unit}/heads', [\App\Http\Controllers\HR\UnitHeadController::class, 'index'])
        ->name('heads.index');
    Route::post('/heads', [\App\Http\Controllers\HR\UnitHeadController::class, 'store'])
        ->name('heads.store');
    Route::put('/heads/{head}', [\App\Http\Controllers\HR\UnitHeadController::class, 'update'])
        ->name('heads.update');
    Route::patch('/heads/{head}/end', [\App\Http\Controllers\HR\UnitHeadController::class, 'end'])
        ->name('heads.end');
    Route::delete('/heads/{head}', [\App\Http\Controllers\HR\UnitHeadController::class, 'destroy'])
        ->name('heads.destroy');

    // ── Sync from legacy divisions/offices ─────────────────────────────────────
    Route::post('/sync-legacy', [\App\Http\Controllers\HR\OrgUnitController::class, 'syncFromLegacy'])
        ->name('sync-legacy');

    // ── Reports ────────────────────────────────────────────────────────────────
    Route::get('/reports', [\App\Http\Controllers\HR\OrgUnitController::class, 'reports'])
        ->name('reports');

    // ── Export & Print ──────────────────────────────────────────────────────────
    Route::get('/print', [\App\Http\Controllers\HR\OrgExportController::class, 'print'])
        ->name('print');
    Route::get('/export/pdf', [\App\Http\Controllers\HR\OrgExportController::class, 'pdf'])
        ->name('export.pdf');
    Route::get('/export/units-csv', [\App\Http\Controllers\HR\OrgExportController::class, 'unitsCsv'])
        ->name('export.units-csv');
    Route::get('/export/assignments-csv', [\App\Http\Controllers\HR\OrgExportController::class, 'assignmentsCsv'])
        ->name('export.assignments-csv');

    // ── Structural versioning ───────────────────────────────────────────────────
    Route::get('/versions', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'index'])
        ->name('versions.index');
    Route::get('/versions/current', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'current'])
        ->name('versions.current');
    Route::get('/versions/{version}', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'show'])
        ->name('versions.show');
    Route::post('/versions', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'store'])
        ->name('versions.store');
    Route::post('/versions/{version}/approve', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'approve'])
        ->name('versions.approve');
    Route::post('/versions/{version}/activate', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'activate'])
        ->name('versions.activate');
    Route::delete('/versions/{version}', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'destroy'])
        ->name('versions.destroy');
});

// ── Student Attendance Module ──────────────────────────────────────────────────

// Dual-mode kiosk: student self-scan terminals and guard camera iPads are paired separately.
Route::prefix('student-attendance')->name('student-attendance.')->group(function () {
    Route::get('/kiosk', [\App\Http\Controllers\StudentAttendance\KioskController::class, 'index'])
        ->name('kiosk');
    Route::post('/kiosk/unlock', [\App\Http\Controllers\StudentAttendance\KioskAccessController::class, 'unlock'])
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera'])
        ->name('kiosk.unlock');
    Route::post('/kiosk/lock', [\App\Http\Controllers\StudentAttendance\KioskAccessController::class, 'lock'])
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera'])
        ->name('kiosk.lock');
    Route::post('/scan', [\App\Http\Controllers\StudentAttendance\ScanController::class, 'scan'])
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera', 'attendance.scanner', 'throttle:student-attendance-scan'])
        ->name('scan');
    Route::post('/self-scan', [\App\Http\Controllers\StudentAttendance\ScanController::class, 'selfScan'])
        ->middleware(['attendance.device', 'attendance.device-mode:student_self_scan', 'throttle:student-attendance-scan'])
        ->name('self-scan');
    Route::get('/kiosk/status', [\App\Http\Controllers\StudentAttendance\KioskController::class, 'status'])
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera', 'attendance.scanner'])
        ->name('kiosk.status');
    Route::get('/student/{id}/photo', [\App\Http\Controllers\StudentController::class, 'proxyPhoto'])
        ->whereNumber('id')
        ->middleware(['attendance.device', 'attendance.photo'])
        ->name('student.photo');
    Route::get('/directory/search', [\App\Http\Controllers\StudentAttendance\GuardDirectoryController::class, 'search'])
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera', 'attendance.scanner', 'throttle:student-attendance-directory'])
        ->name('directory.search');
    Route::get('/directory/students/{student}', [\App\Http\Controllers\StudentAttendance\GuardDirectoryController::class, 'student'])
        ->whereNumber('student')
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera', 'attendance.scanner', 'throttle:student-attendance-directory'])
        ->name('directory.students.show');
    Route::post('/directory/students/{student}/reveal-contacts', [\App\Http\Controllers\StudentAttendance\GuardDirectoryController::class, 'revealContacts'])
        ->whereNumber('student')
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera', 'attendance.scanner', 'throttle:student-attendance-contact-reveal'])
        ->name('directory.students.contacts');
    Route::get('/directory/employees/{employee}', [\App\Http\Controllers\StudentAttendance\GuardDirectoryController::class, 'employee'])
        ->whereNumber('employee')
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera', 'attendance.scanner', 'throttle:student-attendance-directory'])
        ->name('directory.employees.show');
    Route::get('/directory/employees/{employee}/photo', [\App\Http\Controllers\StudentAttendance\GuardDirectoryController::class, 'employeePhoto'])
        ->whereNumber('employee')
        ->middleware(['attendance.device', 'attendance.device-mode:guard_camera', 'attendance.scanner'])
        ->name('employee.photo');
});

// Student link confirmation remains public — accessed via emailed link.
Route::prefix('student-attendance')->name('student-attendance.')->group(function () {
    Route::get('/link/{token}',          [\App\Http\Controllers\StudentAttendance\LinkConfirmController::class, 'show'])->name('link.show');
    Route::post('/link/{token}/confirm', [\App\Http\Controllers\StudentAttendance\LinkConfirmController::class, 'confirm'])->name('link.confirm');
    Route::post('/link/{token}/deny',    [\App\Http\Controllers\StudentAttendance\LinkConfirmController::class, 'deny'])->name('link.deny');
});

// Protected: logs and parent contacts management
Route::middleware(['auth'])->prefix('student-attendance')->name('student-attendance.')->group(function () {

    Route::get('/kiosk/admin-setup', fn () => redirect()->route('student-attendance.kiosk'))
        ->middleware('role:Administrator')
        ->name('kiosk.admin-setup');

    Route::post('/devices/pair', [\App\Http\Controllers\StudentAttendance\DeviceController::class, 'pair'])
        ->middleware('role:Administrator')
        ->name('devices.pair');
    Route::post('/devices/finish-setup', [\App\Http\Controllers\StudentAttendance\KioskAccessController::class, 'finishSetup'])
        ->middleware('role:Administrator')
        ->name('devices.finish-setup');

    Route::get('/devices', [\App\Http\Controllers\StudentAttendance\DeviceController::class, 'index'])
        ->name('devices.index')
        ->middleware('role:Administrator');
    Route::post('/devices/{device}/revoke', [\App\Http\Controllers\StudentAttendance\DeviceController::class, 'revoke'])
        ->name('devices.revoke')
        ->middleware('role:Administrator');
    Route::put('/operators/{user}/pin', [\App\Http\Controllers\StudentAttendance\KioskOperatorController::class, 'setPin'])
        ->name('operators.pin')
        ->middleware('role:Administrator');
    Route::put('/operators/{user}/status', [\App\Http\Controllers\StudentAttendance\KioskOperatorController::class, 'updateStatus'])
        ->name('operators.status')
        ->middleware('role:Administrator');

    Route::get('/logs', [\App\Http\Controllers\StudentAttendance\AttendanceLogController::class, 'index'])
        ->name('logs.index')
        ->middleware('permission:students.attendance.view');

    Route::get('/parents', [\App\Http\Controllers\StudentAttendance\ParentContactController::class, 'index'])
        ->name('parents.index')
        ->middleware('permission:students.attendance.view');
    Route::post('/parents', [\App\Http\Controllers\StudentAttendance\ParentContactController::class, 'store'])
        ->name('parents.store')
        ->middleware('permission:students.attendance.view');
    Route::put('/parents/{parentContact}', [\App\Http\Controllers\StudentAttendance\ParentContactController::class, 'update'])
        ->name('parents.update')
        ->middleware('permission:students.attendance.view');
    Route::delete('/parents/{parentContact}', [\App\Http\Controllers\StudentAttendance\ParentContactController::class, 'destroy'])
        ->name('parents.destroy')
        ->middleware('permission:students.attendance.view');
});

// Development-only: send a test email
if (app()->environment('local')) {
    Route::get('/dev/send-test-email', function () {
        \Illuminate\Support\Facades\Mail::raw('Test email from Atlas (dev route)', function ($m) {
            $m->to('mikelfrancisco@gmail.com')->subject('Atlas dev test');
        });
        return response('OK');
    });
}

/*
|--------------------------------------------------------------------------
| Class Record Routes (Inertia pages + JSON API)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/class-records',      [\App\Http\Controllers\ClassRecord\ClassRecordPageController::class, 'index'])->name('class-records.page.index');

    // Weekly Assessment Tracker — must be registered BEFORE the {classRecord} wildcard
    Route::get('/class-records/wat',         [\App\Http\Controllers\ClassRecord\WeeklyAssessmentTrackerController::class, 'index'])->name('class-records.wat.index');
    Route::get('/class-records/wat/my-tracker', [\App\Http\Controllers\ClassRecord\WeeklyAssessmentTrackerController::class, 'myTracker'])->name('class-records.wat.my-tracker');
    Route::get('/class-records/wat/print',   [\App\Http\Controllers\ClassRecord\WeeklyAssessmentTrackerController::class, 'printForm'])->name('class-records.wat.print');
    Route::get('/class-records/wat/review',  [\App\Http\Controllers\ClassRecord\WeeklyAssessmentTrackerController::class, 'review'])->name('class-records.wat.review');
    Route::post('/class-records/wat/review', [\App\Http\Controllers\ClassRecord\WeeklyAssessmentTrackerController::class, 'storeReview'])->name('class-records.wat.review.store');

    // CID Chief / Academic Unit Head read-only monitoring dashboard — must be
    // registered BEFORE the {classRecord} wildcard
    Route::get('/class-records/monitoring', [\App\Http\Controllers\ClassRecord\ClassRecordMonitorController::class, 'index'])->name('class-records.monitor.index');

    Route::get('/class-records/{classRecord}', [\App\Http\Controllers\ClassRecord\ClassRecordPageController::class, 'show'])->name('class-records.page.show');
});
// ── PSGC Address Lookup (public geographic data, no auth required) ────────────
Route::prefix('api/psgc')->name('psgc.')->controller(\App\Http\Controllers\PSGCController::class)->group(function () {
    Route::get('regions',   'regions');
    Route::get('provinces', 'provinces');
    Route::get('cities',    'cities');
    Route::get('barangays', 'barangays');
});

Route::middleware(['auth'])->prefix('api/v1')->group(function () {
    // Reference data — grading options
    Route::get('/grading-options',                            [\App\Http\Controllers\ClassRecord\GradingOptionController::class, 'index'])->name('grading-options.index');
    Route::post('/grading-options',                           [\App\Http\Controllers\ClassRecord\GradingOptionController::class, 'store'])->name('grading-options.store');
    Route::put('/grading-options/{gradingOption}',            [\App\Http\Controllers\ClassRecord\GradingOptionController::class, 'update'])->name('grading-options.update');
    Route::put('/grading-options/{gradingOption}/categories', [\App\Http\Controllers\ClassRecord\GradingOptionController::class, 'updateCategories'])->name('grading-options.categories.update');
    Route::delete('/grading-options/{gradingOption}',         [\App\Http\Controllers\ClassRecord\GradingOptionController::class, 'destroy'])->name('grading-options.destroy');

    // Quarter final exam windows (WAT cap + schedule-day exemption)
    Route::get('/quarter-exam-windows',                          [\App\Http\Controllers\ClassRecord\QuarterExamWindowController::class, 'index'])->name('quarter-exam-windows.index');
    Route::put('/quarter-exam-windows',                          [\App\Http\Controllers\ClassRecord\QuarterExamWindowController::class, 'upsert'])->name('quarter-exam-windows.upsert');
    Route::delete('/quarter-exam-windows/{quarterExamWindow}',    [\App\Http\Controllers\ClassRecord\QuarterExamWindowController::class, 'destroy'])->name('quarter-exam-windows.destroy');

    // Teaching load pre-fill (must be before {classRecord} route)
    Route::get('/class-records/my-teaching-load', [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'myTeachingLoad'])->name('class-records.my-teaching-load');

    // Class Records CRUD + workflow
    Route::get('/class-records',                        [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'index'])->name('class-records.index');
    Route::post('/class-records',                       [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'store'])->name('class-records.store');
    Route::post('/class-records/bulk',                  [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'bulkStore'])->name('class-records.bulk-store');
    Route::post('/class-records/{classRecord}/join',    [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'join'])->name('class-records.join');
    Route::get('/class-records/{classRecord}/co-teacher-candidates', [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'coTeacherCandidates'])->name('class-records.co-teacher-candidates');
    Route::get('/class-records/{classRecord}',          [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'show'])->name('class-records.show');
    Route::put('/class-records/{classRecord}',          [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'update'])->name('class-records.update');
    Route::delete('/class-records/{classRecord}',       [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'destroy'])->name('class-records.destroy');
    Route::post('/class-records/{classRecord}/restore', [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'restore'])->name('class-records.restore');
    Route::post('/class-records/{classRecord}/submit',  [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'submit'])->name('class-records.submit');
    Route::post('/class-records/{classRecord}/check',   [\App\Http\Controllers\ClassRecord\ClassRecordController::class, 'check'])->name('class-records.check');

    // Quarter management
    Route::get('/class-records/{classRecord}/quarters/{q}',        [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'show'])->name('class-records.quarters.show');
    Route::post('/class-records/{classRecord}/quarters/{q}/lock',   [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'lock'])->name('class-records.quarters.lock');
    Route::post('/class-records/{classRecord}/quarters/{q}/unlock', [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'unlock'])->name('class-records.quarters.unlock');
    Route::put('/class-records/{classRecord}/quarters/{q}/grading-option', [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'setGradingOption'])->name('class-records.quarters.grading-option');
    Route::get('/class-records/{classRecord}/quarters/{q}/grades',  [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'grades'])->name('class-records.quarters.grades');

    // Assessments
    Route::get('/class-records/{classRecord}/quarters/{q}/assessments',                   [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'index'])->name('class-records.assessments.index');
    Route::post('/class-records/{classRecord}/quarters/{q}/assessments',                   [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'upsert'])->name('class-records.assessments.upsert');
    Route::post('/class-records/{classRecord}/quarters/{q}/assessments/plot',              [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'plot'])->name('class-records.assessments.plot');
    Route::post('/class-records/{classRecord}/quarters/{q}/assessments/copy-from',        [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'copyFrom'])->name('class-records.assessments.copy-from');
    Route::post('/class-records/{classRecord}/quarters/{q}/assessments/copy-from-record', [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'copyFromRecord'])->name('class-records.assessments.copy-from-record');
    Route::post('/class-records/{classRecord}/quarters/{q}/assessments/apply-to-sections', [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'applyToSections'])->name('class-records.assessments.apply-to-sections');
    Route::post('/class-records/{classRecord}/quarters/{q}/assessments/{assessment}/request-deletion', [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'requestDeletion'])->name('class-records.assessments.request-deletion');
    Route::get('/class-records/{classRecord}/section-calendar', [\App\Http\Controllers\ClassRecord\ClassRecordAssessmentController::class, 'sectionCalendar'])->name('class-records.assessments.section-calendar');

    // Students
    Route::get('/class-records/{classRecord}/quarters/{q}/students/from-enrollment', [\App\Http\Controllers\ClassRecord\ClassRecordStudentController::class, 'fromEnrollment'])->name('class-records.students.from-enrollment');
    Route::get('/class-records/{classRecord}/quarters/{q}/students/candidates', [\App\Http\Controllers\ClassRecord\ClassRecordStudentController::class, 'candidates'])->name('class-records.students.candidates');
    Route::get('/class-records/{classRecord}/quarters/{q}/students',          [\App\Http\Controllers\ClassRecord\ClassRecordStudentController::class, 'index'])->name('class-records.students.index');
    Route::post('/class-records/{classRecord}/quarters/{q}/students',         [\App\Http\Controllers\ClassRecord\ClassRecordStudentController::class, 'upsert'])->name('class-records.students.upsert');
    Route::get('/class-records/{classRecord}/quarters/{q}/students/template', [\App\Http\Controllers\ClassRecord\ClassRecordStudentController::class, 'template'])->name('class-records.students.template');
    Route::post('/class-records/{classRecord}/quarters/{q}/students/import',  [\App\Http\Controllers\ClassRecord\ClassRecordStudentController::class, 'import'])->name('class-records.students.import');
    Route::post('/class-records/{classRecord}/quarters/{q}/students/{classRecordStudent}/transfer', [\App\Http\Controllers\ClassRecord\ClassRecordStudentController::class, 'transfer'])->name('class-records.students.transfer');

    // Scores
    Route::get('/class-records/{classRecord}/quarters/{q}/scores',  [\App\Http\Controllers\ClassRecord\ClassRecordScoreController::class, 'index'])->name('class-records.scores.index');
    Route::post('/class-records/{classRecord}/quarters/{q}/scores', [\App\Http\Controllers\ClassRecord\ClassRecordScoreController::class, 'upsert'])->name('class-records.scores.upsert');

    // Attendance
    Route::get('/class-records/{classRecord}/quarters/{q}/attendance',              [\App\Http\Controllers\ClassRecord\ClassRecordAttendanceController::class, 'index'])->name('class-records.attendance.index');
    Route::post('/class-records/{classRecord}/quarters/{q}/attendance/dates',       [\App\Http\Controllers\ClassRecord\ClassRecordAttendanceController::class, 'storeDates'])->name('class-records.attendance.store-dates');
    Route::delete('/class-records/{classRecord}/quarters/{q}/attendance/dates/{attendanceDate}', [\App\Http\Controllers\ClassRecord\ClassRecordAttendanceController::class, 'destroyDate'])->name('class-records.attendance.destroy-date');
    Route::post('/class-records/{classRecord}/quarters/{q}/attendance/records',     [\App\Http\Controllers\ClassRecord\ClassRecordAttendanceController::class, 'upsert'])->name('class-records.attendance.upsert');
    Route::get('/class-records/{classRecord}/quarters/{q}/attendance/records/{attendanceRecord}/clinic-consultations', [\App\Http\Controllers\ClassRecord\ClassRecordAttendanceController::class, 'clinicConsultations'])->name('class-records.attendance.clinic-consultations');
    Route::post('/class-records/{classRecord}/quarters/{q}/attendance/records/{attendanceRecord}/excuse-via-clinic', [\App\Http\Controllers\ClassRecord\ClassRecordAttendanceController::class, 'excuseViaClinic'])->name('class-records.attendance.excuse-via-clinic');

    // Independent Learning Activity (ILA) — auto-generated from scheduled ILP periods
    Route::get('/class-records/{classRecord}/quarters/{q}/ila',              [\App\Http\Controllers\ClassRecord\ClassRecordIlaController::class, 'index'])->name('class-records.ila.index');
    Route::post('/class-records/{classRecord}/quarters/{q}/ila/dates',       [\App\Http\Controllers\ClassRecord\ClassRecordIlaController::class, 'storeDate'])->name('class-records.ila.store-date');
    Route::delete('/class-records/{classRecord}/quarters/{q}/ila/dates/{ilaDate}', [\App\Http\Controllers\ClassRecord\ClassRecordIlaController::class, 'destroyDate'])->name('class-records.ila.destroy-date');
    Route::patch('/class-records/{classRecord}/quarters/{q}/ila/dates/{ilaDate}/title', [\App\Http\Controllers\ClassRecord\ClassRecordIlaController::class, 'updateTitle'])->name('class-records.ila.update-title');
    Route::post('/class-records/{classRecord}/quarters/{q}/ila/records',     [\App\Http\Controllers\ClassRecord\ClassRecordIlaController::class, 'upsert'])->name('class-records.ila.upsert');
    Route::post('/class-records/{classRecord}/quarters/{q}/ila/dates/{ilaDate}/grade',   [\App\Http\Controllers\ClassRecord\ClassRecordIlaController::class, 'gradeDate'])->name('class-records.ila.grade-date');
    Route::delete('/class-records/{classRecord}/quarters/{q}/ila/dates/{ilaDate}/grade', [\App\Http\Controllers\ClassRecord\ClassRecordIlaController::class, 'ungradeDate'])->name('class-records.ila.ungrade-date');

    // Final grades
    Route::get('/class-records/{classRecord}/final-grades', [\App\Http\Controllers\ClassRecord\ClassRecordFinalGradeController::class, 'index'])->name('class-records.final-grades');

    // Export
    Route::get('/class-records/{classRecord}/quarters/{q}/export', [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'exportQuarter'])->name('class-records.quarters.export');
    Route::get('/class-records/{classRecord}/quarters/{q}/pdf',    [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'exportPdf'])->name('class-records.quarters.pdf');
    Route::get('/class-records/{classRecord}/export',              [\App\Http\Controllers\ClassRecord\ClassRecordQuarterController::class, 'exportAll'])->name('class-records.export');
});

/*
|--------------------------------------------------------------------------
| Learn Module (LMS) — Phase 1: Course Shell + Content
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('learn')->name('learn.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Learn\CourseController::class, 'index'])->name('index');

    // Must be registered before the {course} wildcard.
    Route::get('/file/{fileId}', [\App\Http\Controllers\Learn\FileController::class, 'show'])
        ->name('files.show')->where('fileId', '[a-zA-Z0-9_.=-]+');

    Route::get('/{course}', [\App\Http\Controllers\Learn\CourseController::class, 'show'])->name('show');
    Route::put('/{course}/syllabus', [\App\Http\Controllers\Learn\CourseController::class, 'updateSyllabus'])->name('syllabus.update');
    Route::patch('/{course}/status', [\App\Http\Controllers\Learn\CourseController::class, 'updateStatus'])->name('status.update');

    Route::post('/{course}/modules', [\App\Http\Controllers\Learn\ModuleController::class, 'store'])->name('modules.store');
    Route::put('/{course}/modules/reorder', [\App\Http\Controllers\Learn\ModuleController::class, 'reorder'])->name('modules.reorder');
    Route::put('/modules/{module}', [\App\Http\Controllers\Learn\ModuleController::class, 'update'])->name('modules.update');
    Route::patch('/modules/{module}/publish', [\App\Http\Controllers\Learn\ModuleController::class, 'togglePublish'])->name('modules.publish');
    Route::delete('/modules/{module}', [\App\Http\Controllers\Learn\ModuleController::class, 'destroy'])->name('modules.destroy');

    Route::post('/modules/{module}/items/page', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storePage'])->name('items.store-page');
    Route::post('/modules/{module}/items/file', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storeFile'])->name('items.store-file');
    Route::post('/modules/{module}/items/assignment', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storeAssignment'])->name('items.store-assignment');

    Route::get('/assignments/{assignment}/submissions', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'index'])->name('assignments.submissions');
    Route::put('/submissions/{submission}/grade', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'grade'])->name('submissions.grade');
    Route::post('/submissions/{submission}/reopen', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'reopen'])->name('submissions.reopen');
    Route::get('/submissions/{submission}/file', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'file'])->name('submissions.file');
    Route::put('/modules/{module}/items/reorder', [\App\Http\Controllers\Learn\ModuleItemController::class, 'reorder'])->name('items.reorder');
    Route::patch('/items/{item}/publish', [\App\Http\Controllers\Learn\ModuleItemController::class, 'togglePublish'])->name('items.publish');
    Route::delete('/items/{item}', [\App\Http\Controllers\Learn\ModuleItemController::class, 'destroy'])->name('items.destroy');

    Route::post('/{course}/announcements', [\App\Http\Controllers\Learn\CourseAnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [\App\Http\Controllers\Learn\CourseAnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [\App\Http\Controllers\Learn\CourseAnnouncementController::class, 'destroy'])->name('announcements.destroy');
});

/*
|--------------------------------------------------------------------------
| Document Verification — public, no auth required
|--------------------------------------------------------------------------
*/
// Employee Digital ID verification (public) — opaque 48-char token from the ID QR
Route::get('/verify/employee/{token}', [\App\Http\Controllers\DocumentVerificationController::class, 'showEmployee'])
    ->name('employee.verify')
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]{48}');

Route::get('/verify/{token}', [\App\Http\Controllers\DocumentVerificationController::class, 'show'])
    ->name('document.verify')
    ->where('token', '[0-9a-f\-]{36}');

Route::get('/verify/itjr/{itjrNo}', [\App\Http\Controllers\DocumentVerificationController::class, 'showItjr'])
    ->name('itjr.verify')
    ->where('itjrNo', '[0-9\-]+');

Route::get('/verify/issuance/{token}', [\App\Http\Controllers\DocumentVerificationController::class, 'showIssuance'])
    ->name('issuances.verify')
    ->where('token', '[0-9a-f\-]{36}');

Route::get('/verify/issuance/{token}/document', [\App\Http\Controllers\DocumentVerificationController::class, 'issuanceDocument'])
    ->name('issuances.verify.document')
    ->where('token', '[0-9a-f\-]{36}');

Route::get('/verify/coa/{token}', [\App\Http\Controllers\DocumentVerificationController::class, 'showCoa'])
    ->name('coa.verify')
    ->middleware('throttle:30,1')
    ->where('token', '[0-9a-f\-]{36}');

Route::get('/verify/coa/{token}/document', [\App\Http\Controllers\DocumentVerificationController::class, 'coaDocument'])
    ->name('coa.verify.document')
    ->middleware('throttle:30,1')
    ->where('token', '[0-9a-f\-]{36}');

Route::get('/verify/doc/{type}/{id}', [\App\Http\Controllers\DocumentVerificationController::class, 'showDocument'])
    ->name('document.verify.doc')
    ->whereIn('type', ['facility', 'vehicle', 'work-request', 'service', 'gatepass', 'leave', 'messengerial'])
    ->where('id', '[0-9]+');

// ── Student Portal ────────────────────────────────────────────────────────────
// Public (no session required): login page + Google OAuth + logout
Route::prefix('student-portal')->name('student-portal.')->group(function () {
    Route::get('/',        [\App\Http\Controllers\StudentPortal\AuthController::class, 'showLogin'])->name('login');
    Route::get('/login',   [\App\Http\Controllers\StudentPortal\AuthController::class, 'showLogin'])->name('login.page');
    Route::post('/auth/firebase', [\App\Http\Controllers\StudentPortal\AuthController::class, 'handleFirebaseAuth'])->name('auth.firebase');
    Route::post('/logout', [\App\Http\Controllers\StudentPortal\AuthController::class, 'logout'])->name('logout');

    // Requires Google auth (session has google_email) but not yet linked
    Route::get('/link',  [\App\Http\Controllers\StudentPortal\AuthController::class, 'showLink'])->name('link');
    Route::post('/link', [\App\Http\Controllers\StudentPortal\AuthController::class, 'submitLink'])->name('link.submit');

    // Requires full student session (linked + authenticated)
    Route::middleware('student.portal')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\StudentPortal\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/profile',                   [\App\Http\Controllers\StudentPortal\ProfileController::class, 'show'])->name('profile');
        Route::post('/profile/{section}',        [\App\Http\Controllers\StudentPortal\ProfileController::class, 'saveSection'])->name('profile.save')
            ->where('section', 'academic|activities|social|career|residence|health');

        Route::get('/medical',                   [\App\Http\Controllers\StudentPortal\MedicalController::class, 'show'])->name('medical');
        Route::post('/medical/{section}',        [\App\Http\Controllers\StudentPortal\MedicalController::class, 'saveSection'])->name('medical.save')
            ->where('section', 'allergies|immunizations|medical_history|vitamins');

        Route::get('/rh-application',  [\App\Http\Controllers\StudentPortal\RhApplicationController::class, 'show'])->name('rh-application.show');
        Route::post('/rh-application', [\App\Http\Controllers\StudentPortal\RhApplicationController::class, 'store'])->name('rh-application.store');

        Route::get('/leave-passes',  [\App\Http\Controllers\StudentPortal\RhLeavePassController::class, 'index'])->name('rh-leave-passes.index');
        Route::post('/leave-passes', [\App\Http\Controllers\StudentPortal\RhLeavePassController::class, 'store'])->name('rh-leave-passes.store');

        Route::get('/lost-found',               [\App\Http\Controllers\StudentPortal\LostFoundController::class, 'show'])->name('lost-found');
        Route::post('/lost-found',              [\App\Http\Controllers\StudentPortal\LostFoundController::class, 'store'])->name('lost-found.store');
        Route::get('/lost-found/photo/{item}',  [\App\Http\Controllers\StudentPortal\LostFoundController::class, 'photo'])->name('lost-found.photo')->whereNumber('item');

        // Must be registered before the {course} wildcard.
        Route::get('/learn/file/{fileId}', [\App\Http\Controllers\StudentPortal\LearnController::class, 'file'])
            ->name('learn.file')->where('fileId', '[a-zA-Z0-9_.=-]+');
        Route::get('/learn', [\App\Http\Controllers\StudentPortal\LearnController::class, 'index'])->name('learn.index');
        Route::get('/learn/{course}', [\App\Http\Controllers\StudentPortal\LearnController::class, 'show'])->name('learn.show');
        Route::post('/learn/assignments/{assignment}/submit', [\App\Http\Controllers\StudentPortal\LearnController::class, 'submit'])->name('learn.assignments.submit');
        Route::get('/learn/submissions/{submission}/file', [\App\Http\Controllers\StudentPortal\LearnController::class, 'submissionFile'])->name('learn.submissions.file');
    });
});

// ── Teacher Class Attendance (NFC Tap-In) ────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    // NFC tap endpoint — NFC tag encodes /class-tap/{uuid}, auto-logs on load.
    // nfc_uuid is never printed/exposed via the QR — this route is only ever
    // reachable by physically tapping the tag.
    Route::get('/class-tap/{uuid}', [\App\Http\Controllers\TeacherAttendanceController::class, 'tap'])
        ->name('class-tap')
        ->where('uuid', '[0-9a-f\-]{36}');

    // QR tap endpoint — printed card encodes /class-tap-qr/{qr_token}, a
    // separate secret from nfc_uuid. Always renders the confirm page, which
    // gates on location/network before any attendance is recorded.
    Route::get('/class-tap-qr/{qrToken}', [\App\Http\Controllers\TeacherAttendanceController::class, 'tapViaQr'])
        ->name('class-tap-qr')
        ->where('qrToken', '[A-Za-z0-9]{40}');

    Route::post('/class-tap-qr/{qrToken}/confirm', [\App\Http\Controllers\TeacherAttendanceController::class, 'confirmQrTap'])
        ->name('class-tap-qr.confirm')
        ->where('qrToken', '[A-Za-z0-9]{40}');

    // Monitoring dashboard (AUH / CID Chief / Admin)
    Route::get('/teacher-attendance',        [\App\Http\Controllers\TeacherAttendanceController::class, 'index'])->name('teacher-attendance.index');
    Route::get('/teacher-attendance/export', [\App\Http\Controllers\TeacherAttendanceController::class, 'export'])->name('teacher-attendance.export');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/chat.php';
require __DIR__.'/saln.php';
require __DIR__.'/faculty-loading.php';
require __DIR__.'/ams.php';
require __DIR__.'/quiz.php';
require __DIR__.'/ppmp.php';
require __DIR__.'/procurement.php';
require __DIR__.'/supply.php';
require __DIR__.'/property.php';
require __DIR__.'/registrar.php';
require __DIR__.'/homeroom-attendance.php';
require __DIR__.'/cid.php';
require __DIR__.'/administration.php';
require __DIR__.'/science-lab.php';
require __DIR__.'/discipline.php';
require __DIR__.'/residence-hall.php';
require __DIR__.'/alp.php';
// ── Atlas Module Monitor ──────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:atlas.modules.view'])
    ->prefix('atlas')
    ->name('atlas.')
    ->group(function () {
        Route::get('/modules', [\App\Http\Controllers\Atlas\AtlasModuleController::class, 'index'])
            ->name('modules.index');
        Route::post('/modules/refresh', [\App\Http\Controllers\Atlas\AtlasModuleController::class, 'refresh'])
            ->name('modules.refresh');
        Route::get('/modules/{key}/metrics', [\App\Http\Controllers\Atlas\AtlasModuleController::class, 'moduleMetrics'])
            ->name('modules.metrics');
        Route::post('/modules/{key}/maturity', [\App\Http\Controllers\Atlas\AtlasModuleController::class, 'saveMaturity'])
            ->name('modules.maturity.save');
        Route::post('/modules/{key}/settings', [\App\Http\Controllers\Atlas\AtlasModuleController::class, 'saveSettings'])
            ->name('modules.settings.save');
    });

// ── Atlas WatchTower (app telemetry monitoring) ───────────────────────────────
Route::middleware(['auth', 'permission:atlas.watchtower.view'])
    ->prefix('atlas/watchtower')
    ->name('atlas.watchtower.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Atlas\AtlasWatchTowerController::class, 'index'])
            ->name('index');
        Route::get('/active-users', [\App\Http\Controllers\Atlas\AtlasWatchTowerController::class, 'activeUsers'])
            ->name('active-users');
        Route::get('/users/{user}/activity', [\App\Http\Controllers\Atlas\AtlasWatchTowerController::class, 'userActivity'])
            ->name('user-activity');
    });

// ── Atlas Workspace Sync (Google Workspace directory reconciliation) ──────────
Route::middleware(['auth', 'permission:atlas.workspace-sync.view'])
    ->prefix('atlas/workspace-sync')
    ->name('atlas.workspace-sync.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Atlas\WorkspaceSyncController::class, 'index'])
            ->name('index');

        Route::middleware('permission:atlas.workspace-sync.manage')->group(function () {
            Route::post('/scan', [\App\Http\Controllers\Atlas\WorkspaceSyncController::class, 'runScan'])
                ->name('scan');
            Route::post('/{proposal}/approve', [\App\Http\Controllers\Atlas\WorkspaceSyncController::class, 'approve'])
                ->name('approve');
            Route::post('/{proposal}/reject', [\App\Http\Controllers\Atlas\WorkspaceSyncController::class, 'reject'])
                ->name('reject');
        });
    });

// ── Error Reports ─────────────────────────────────────────────────────────────
Route::middleware('auth')->prefix('error-reports')->name('error-reports.')->group(function () {
    Route::post('/',              [\App\Http\Controllers\ErrorReportController::class, 'store'])      ->name('store');
    Route::get('/my',            [\App\Http\Controllers\ErrorReportController::class, 'myReports'])  ->name('my');
    Route::get('/{errorReport}/screenshot', [\App\Http\Controllers\ErrorReportController::class, 'screenshot'])->name('screenshot');
    Route::middleware('permission:error-reports.manage')->group(function () {
        Route::get('/',              [\App\Http\Controllers\ErrorReportController::class, 'index'])  ->name('index');
        Route::put('/{errorReport}', [\App\Http\Controllers\ErrorReportController::class, 'update'])->name('update');
    });
});

require __DIR__.'/auth.php';
