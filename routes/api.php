<?php

use App\Http\Controllers\StudentAttendance\Api\AttendanceApiController;
use App\Http\Controllers\StudentAttendance\Api\AuthController;
use App\Http\Controllers\StudentAttendance\Api\GradeApiController;
use App\Http\Controllers\StudentAttendance\Api\LinkRequestController;
use App\Http\Controllers\StudentAttendance\Api\RegisterController;
use App\Http\Controllers\StudentAttendance\Api\ScheduleApiController;
use App\Http\Controllers\StudentAttendance\Api\StudentApiController;
use App\Http\Controllers\StudentAttendance\Api\StudentSelfController;
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
        });
    });
});


// ALB health check — always returns 200
Route::get('/_ping', function () {
    return response()->json(['status' => 'ok'], 200);
});
