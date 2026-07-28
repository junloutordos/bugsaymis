<?php

use App\Http\Controllers\HomeroomAttendance\ActivityAttendanceController;
use App\Http\Controllers\HomeroomAttendance\AdmissionSlipController;
use App\Http\Controllers\HomeroomAttendance\DailyAttendanceController;
use App\Http\Controllers\HomeroomAttendance\FlagAttendanceController;
use App\Http\Controllers\HomeroomAttendance\MonthlyReportController;
use App\Http\Controllers\HomeroomAttendance\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Homeroom Attendance Module Routes
|--------------------------------------------------------------------------
|
| Whole-day, homeroom-adviser-level attendance — separate from the
| subject/period-level attendance already tracked inside Class Record.
| Section scope is resolved via AdvisoryScheduleScopeService (Designations
| module), not the legacy Section::adviser column.
|
| Permission scopes:
|   homeroom-attendance.log                 — take daily/activity attendance for own advisory section(s)
|   homeroom-attendance.admission-slip.issue — Registrar: issue Class Admission Slips
|   homeroom-attendance.coordinator-review   — Homeroom Unit Coordinator: approve monthly reports
|   homeroom-attendance.admin                — CID Chief/Administrator: campus-wide oversight
|   homeroom-attendance.settings.manage      — edit deduction-point settings
|
*/

Route::middleware(['auth', 'permission:homeroom-attendance.log'])
    ->prefix('homeroom-attendance')
    ->name('homeroom-attendance.')
    ->group(function () {
        Route::get('/', [DailyAttendanceController::class, 'index'])->name('index');
        Route::post('/', [DailyAttendanceController::class, 'store'])->name('store');

        Route::get('/activities', [ActivityAttendanceController::class, 'index'])->name('activities.index');
        Route::get('/activities/create', [ActivityAttendanceController::class, 'create'])->name('activities.create');
        Route::post('/activities', [ActivityAttendanceController::class, 'store'])->name('activities.store');

        Route::get('/flag', [FlagAttendanceController::class, 'index'])->name('flag.index');
        Route::post('/flag', [FlagAttendanceController::class, 'store'])->name('flag.store');
        Route::get('/flag/{date}/print', [FlagAttendanceController::class, 'print'])->name('flag.print');

        Route::get('/monthly-report', [MonthlyReportController::class, 'show'])->name('monthly-report.show');
        Route::post('/monthly-report/generate', [MonthlyReportController::class, 'generate'])->name('monthly-report.generate');
        Route::post('/monthly-report/{report}/submit', [MonthlyReportController::class, 'submit'])->name('monthly-report.submit');
        Route::get('/monthly-report/{report}/print', [MonthlyReportController::class, 'print'])->name('monthly-report.print');
    });

Route::middleware(['auth', 'permission:homeroom-attendance.coordinator-review'])
    ->prefix('homeroom-attendance')
    ->name('homeroom-attendance.')
    ->group(function () {
        Route::get('/coordinator', [MonthlyReportController::class, 'coordinatorQueue'])->name('coordinator.index');
        Route::post('/monthly-report/{report}/approve', [MonthlyReportController::class, 'approve'])->name('monthly-report.approve');
        Route::post('/monthly-report/{report}/return', [MonthlyReportController::class, 'returnToDraft'])->name('monthly-report.return');
    });

Route::middleware(['auth', 'permission:homeroom-attendance.admission-slip.issue'])
    ->prefix('homeroom-attendance')
    ->name('homeroom-attendance.')
    ->group(function () {
        Route::get('/admission-slips', [AdmissionSlipController::class, 'index'])->name('admission-slips.index');
        Route::post('/admission-slips', [AdmissionSlipController::class, 'store'])->name('admission-slips.store');
        Route::get('/admission-slips/{slip}/print', [AdmissionSlipController::class, 'print'])->name('admission-slips.print');
    });

// The document proxy is reached from a printed/served slip, not gated behind
// the issuing permission alone — anyone with either the issuing permission
// or coordinator/admin oversight may need to view an attached document.
// Bound to the AdmissionSlip model (not a raw S3 key) so the controller can
// only ever stream the document already attached to a real slip.
Route::middleware(['auth', 'permission:homeroom-attendance.admission-slip.issue|homeroom-attendance.admin'])
    ->get('/homeroom-attendance/admission-slips/{slip}/document', [AdmissionSlipController::class, 'document'])
    ->name('homeroom-attendance.admission-slips.document');

Route::middleware(['auth', 'permission:homeroom-attendance.settings.manage'])
    ->prefix('homeroom-attendance')
    ->name('homeroom-attendance.')
    ->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
