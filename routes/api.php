<?php

use App\Http\Controllers\StudentAttendance\Api\AuthController;
use App\Http\Controllers\StudentAttendance\Api\AttendanceApiController;
use App\Http\Controllers\StudentAttendance\Api\StudentApiController;
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

    // ── Public: obtain a Sanctum token ────────────────────────────────────────
    Route::post('/login',  [AuthController::class, 'login'])->name('login');

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::delete('/logout', [AuthController::class, 'logout'])->name('logout');

        // Update FCM device token (called on app startup / token refresh)
        Route::put('/fcm-token', [AuthController::class, 'updateFcmToken'])->name('fcm-token');

        // List all students linked to this parent
        Route::get('/students', [StudentApiController::class, 'index'])->name('students.index');

        // Look up a student by barcode (for parent to link their child)
        Route::get('/students/{barcode}', [StudentApiController::class, 'findByBarcode'])->name('students.find');

        // Link / unlink a student to this parent contact
        Route::post('/students/{barcode}/link',   [StudentApiController::class, 'link'])->name('students.link');
        Route::delete('/students/{barcode}/link', [StudentApiController::class, 'unlink'])->name('students.unlink');

        // Paginated attendance log for all linked students
        Route::get('/attendance', [AttendanceApiController::class, 'index'])->name('attendance.index');

        // Attendance summary for a specific student (today's IN/OUT)
        Route::get('/attendance/{studentId}/today', [AttendanceApiController::class, 'today'])->name('attendance.today');
    });
});
