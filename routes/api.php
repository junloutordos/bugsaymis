<?php

use App\Http\Controllers\StudentAttendance\Api\AttendanceApiController;
use App\Http\Controllers\StudentAttendance\Api\AuthController;
use App\Http\Controllers\StudentAttendance\Api\GoogleAuthController;
use App\Http\Controllers\StudentAttendance\Api\GradeApiController;
use App\Http\Controllers\StudentAttendance\Api\LinkRequestController;
use App\Http\Controllers\StudentAttendance\Api\RegisterController;
use App\Http\Controllers\StudentAttendance\Api\ScheduleApiController;
use App\Http\Controllers\StudentAttendance\Api\StudentApiController;
use App\Http\Controllers\StudentAttendance\Api\StudentPortalApiController;
use App\Http\Controllers\StudentAttendance\Api\StudentSelfController;
use App\Http\Controllers\Api\AtlasSentinelController;
use App\Http\Controllers\Api\AtlasSentinelRemoteHelpController;
use App\Http\Controllers\Api\BiometricPunchIngestController;
use App\Http\Controllers\Api\DynaAuthController;
use App\Http\Controllers\Api\DynaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes — Student Attendance Parent App
|--------------------------------------------------------------------------
| All routes use the `api` middleware group (stateless, Sanctum token auth).
| Prefix: /api  (configured in bootstrap/app.php)
|
| Auth routes:   POST /api/mobile/login
|                DELETE /api/mobile/logout
| Student:       GET  /api/mobile/students/{barcode}
| Attendance:    GET  /api/mobile/attendance
| FCM token:     PUT  /api/mobile/fcm-token
*/

Route::prefix('mobile')->name('mobile.')->group(function () {

    // ── Public ────────────────────────────────────────────────────────────────
    Route::post('/login',               [AuthController::class,  'login'])->name('login')->middleware('throttle:10,1');
    Route::post('/student/google-login',[GoogleAuthController::class, 'login'])->name('student.google-login')->middleware('throttle:10,1');
    Route::post('/student/google-link', [GoogleAuthController::class, 'link'])->name('student.google-link')->middleware('throttle:10,1');
    Route::post('/register',            [RegisterController::class, 'register'])->name('register')->middleware('throttle:5,1');
    Route::post('/student/register',    [RegisterController::class, 'registerStudent'])->name('student.register')->middleware('throttle:5,1');
    Route::post('/verify-email',        [RegisterController::class, 'verifyEmail'])->name('verify-email')->middleware('throttle:5,1');
    Route::post('/resend-verification', [RegisterController::class, 'resendVerification'])->name('resend-verification')->middleware('throttle:3,1');

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::delete('/logout', [AuthController::class, 'logout'])->name('logout');

        // Update FCM device token (called on app startup / token refresh)
        Route::put('/fcm-token', [AuthController::class, 'updateFcmToken'])->name('fcm-token');

        // Notification preferences (push + email toggles)
        Route::get('/notification-preferences',  [AuthController::class, 'getNotificationPreferences'])->name('notification-preferences.show');
        Route::put('/notification-preferences',  [AuthController::class, 'updateNotificationPreferences'])->name('notification-preferences.update');

        // List all students linked to this parent
        Route::get('/students', [StudentApiController::class, 'index'])->name('students.index');

        // Look up a student by barcode (for parent to link their child)
        Route::get('/students/{barcode}', [StudentApiController::class, 'findByBarcode'])->name('students.find');

        // Link / unlink a student to this parent contact
        Route::post('/students/{barcode}/link',   [StudentApiController::class, 'link'])->name('students.link');
        Route::delete('/students/{barcode}/link', [StudentApiController::class, 'unlink'])->name('students.unlink');

        // Secure child linking via student email confirmation
        Route::get('/link-requests',        [LinkRequestController::class, 'index'])->name('link-requests.index');
        Route::post('/link-requests',       [LinkRequestController::class, 'store'])->name('link-requests.store');
        Route::delete('/link-requests/{id}',[LinkRequestController::class, 'destroy'])->name('link-requests.destroy');

        // Paginated attendance log for all linked students
        Route::get('/attendance', [AttendanceApiController::class, 'index'])->name('attendance.index');

        // Attendance summary for a specific student (today's IN/OUT)
        Route::get('/attendance/{studentId}/today', [AttendanceApiController::class, 'today'])->name('attendance.today');

        // Grades for a specific linked student (current school year)
        Route::get('/students/{studentId}/grades', [GradeApiController::class, 'show'])->name('students.grades');

        // Class schedule for a specific linked student (current school year)
        Route::get('/students/{studentId}/schedule', [ScheduleApiController::class, 'show'])->name('students.schedule');

        // ── Student self-access (logged-in as student) ────────────────────────
        Route::prefix('student')->name('student.')->group(function () {
            Route::get('/profile',    [StudentSelfController::class, 'profile'])->name('profile');
            Route::get('/grades',     [StudentSelfController::class, 'grades'])->name('grades');
            Route::get('/schedule',   [StudentSelfController::class, 'schedule'])->name('schedule');
            Route::get('/attendance', [StudentSelfController::class, 'attendance'])->name('attendance');

            // Student-portal features for the AtlasGo app — mirrors the
            // /student-portal web routes via shared StudentPortal services.
            Route::prefix('portal')->name('portal.')->group(function () {
                Route::get('/dashboard', [StudentPortalApiController::class, 'dashboard'])->name('dashboard');

                Route::get('/profile', [StudentPortalApiController::class, 'profile'])->name('profile');
                Route::post('/profile/{section}', [StudentPortalApiController::class, 'saveProfileSection'])->name('profile.save')
                    ->where('section', 'academic|activities|social|career|residence|health')
                    ->middleware('throttle:30,1');

                Route::get('/medical', [StudentPortalApiController::class, 'medical'])->name('medical');
                Route::post('/medical/{section}', [StudentPortalApiController::class, 'saveMedicalSection'])->name('medical.save')
                    ->where('section', 'allergies|immunizations|medical_history|vitamins')
                    ->middleware('throttle:30,1');

                Route::get('/rh-application', [StudentPortalApiController::class, 'rhApplication'])->name('rh-application.show');
                Route::post('/rh-application', [StudentPortalApiController::class, 'storeRhApplication'])->name('rh-application.store')
                    ->middleware('throttle:6,1');

                Route::get('/leave-passes', [StudentPortalApiController::class, 'leavePasses'])->name('leave-passes.index');
                Route::post('/leave-passes', [StudentPortalApiController::class, 'storeLeavePass'])->name('leave-passes.store')
                    ->middleware('throttle:6,1');

                Route::get('/clearance', [StudentPortalApiController::class, 'clearance'])->name('clearance');
                Route::get('/clearance/pdf', [StudentPortalApiController::class, 'clearancePdf'])->name('clearance.pdf');

                Route::get('/lost-found', [StudentPortalApiController::class, 'lostFound'])->name('lost-found.index');
                Route::post('/lost-found', [StudentPortalApiController::class, 'storeLostFoundReport'])->name('lost-found.store')
                    ->middleware('throttle:6,1');
                Route::get('/lost-found/photo/{item}', [StudentPortalApiController::class, 'lostFoundPhoto'])->name('lost-found.photo')
                    ->whereNumber('item');
            });
        });
    });
});


// ALB health check — always returns 200
Route::get('/_ping', function () {
    return response()->json(['status' => 'ok'], 200);
});

/*
|--------------------------------------------------------------------------
| Atlas Sentinel Routes
|--------------------------------------------------------------------------
| Windows desktop agent installed by MIS. Enrollment exchanges a one-time
| token (generated from the ICT Equipments page) for a long-lived device
| Sanctum token; everything after that is authenticated as that specific
| device (see EnsureAtlasSentinelDevice middleware — no cross-device access).
|
| NOTE: the 'ict-agent' URL prefix/route-name and the 'ict-agent' Sanctum
| ability string below are intentionally NOT renamed — they're the wire
| contract already baked into every enrolled device's installed agent and
| issued device token. Renaming them requires a coordinated client+server
| rollout; see the Atlas Sentinel rename plan.
*/
Route::prefix('ict-agent')->name('ict-agent.')->group(function () {
    Route::post('/enroll', [AtlasSentinelController::class, 'enroll'])
        ->name('enroll')
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'ict-agent'])->group(function () {
        Route::get('/me', [AtlasSentinelController::class, 'me'])->name('me');
        Route::post('/checkin', [AtlasSentinelController::class, 'checkin'])->name('checkin');
        Route::post('/inventory-checkin', [AtlasSentinelController::class, 'inventoryCheckin'])->name('inventory-checkin');
        Route::get('/alerts', [AtlasSentinelController::class, 'alerts'])->name('alerts');
        Route::post('/alerts/{alert}/escalate', [AtlasSentinelController::class, 'escalate'])->name('alerts.escalate');
        Route::get('/device-summary', [AtlasSentinelController::class, 'deviceSummary'])->name('device-summary');
        Route::post('/security-incident', [AtlasSentinelController::class, 'reportSecurityIncident'])->name('security-incident');
        Route::post('/biometric-punches', [BiometricPunchIngestController::class, 'store'])->name('biometric-punches.store');
        Route::post('/report-problem', [AtlasSentinelController::class, 'reportProblem'])
            ->name('report-problem')
            ->middleware('throttle:6,1');
        Route::get('/releases/{encodedKey}', [AtlasSentinelController::class, 'releaseDownload'])->name('releases.show');

        // Remote help ("Ask for Remote Help") — attended AnyDesk sessions.
        // `current` (GET) is declared before the wildcard {helpRequest} POSTs so
        // the literal segment can never be swallowed by the binding.
        Route::get('/remote-help/current', [AtlasSentinelRemoteHelpController::class, 'current'])->name('remote-help.current');
        Route::post('/remote-help', [AtlasSentinelRemoteHelpController::class, 'request'])
            ->name('remote-help.request')
            ->middleware('throttle:6,1');
        Route::post('/remote-help/{helpRequest}/report-id', [AtlasSentinelRemoteHelpController::class, 'reportId'])->name('remote-help.report-id');
        Route::post('/remote-help/{helpRequest}/cancel', [AtlasSentinelRemoteHelpController::class, 'cancel'])->name('remote-help.cancel');
        Route::post('/remote-help/{helpRequest}/session-ended', [AtlasSentinelRemoteHelpController::class, 'sessionEnded'])->name('remote-help.session-ended');

        // Document backup — run dispatched via the checkin response; file
        // bytes go straight to Google Drive via server-issued resumable
        // sessions, never through these endpoints.
        Route::post('/backup/{run}/manifest', [AtlasSentinelController::class, 'backupManifest'])->name('backup.manifest');
        Route::post('/backup/{run}/upload-session', [AtlasSentinelController::class, 'backupUploadSession'])->name('backup.upload-session');
        Route::post('/backup/{run}/file-complete', [AtlasSentinelController::class, 'backupFileComplete'])->name('backup.file-complete');
        Route::post('/backup/{run}/complete', [AtlasSentinelController::class, 'backupComplete'])->name('backup.complete');
    });
});

/*
|--------------------------------------------------------------------------
| Dyna AI Assistant — macOS app API
|--------------------------------------------------------------------------
| Stateless, Sanctum token auth. Prefix: /api/dyna
*/

Route::prefix('dyna')->name('dyna.')->group(function () {
    Route::post('/login', [DynaAuthController::class, 'login'])->name('login')->middleware('throttle:10,1');
    Route::post('/login/google', [DynaAuthController::class, 'loginWithGoogle'])->name('login.google')->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'permission:atlas.dyna.access'])->group(function () {
        Route::post('/chat', [DynaController::class, 'chat'])->name('chat');
        Route::get('/conversations', [DynaController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{id}', [DynaController::class, 'show'])->name('conversations.show');
    });
});
