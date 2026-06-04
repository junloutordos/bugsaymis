<?php

use App\Http\Controllers\Registrar\AnalyticsController;
use App\Http\Controllers\Registrar\EnrollmentApplicationController;
use App\Http\Controllers\Registrar\EnrollmentController;
use App\Http\Controllers\Registrar\EnrollmentPeriodController;
use App\Http\Controllers\Registrar\PromotionController;
use App\Http\Controllers\Registrar\ReportCardController;
use App\Http\Controllers\Registrar\RetentionPolicyController;
use App\Http\Controllers\Registrar\SectionAssignmentController;
use App\Http\Controllers\Registrar\StudentDocumentController;
use App\Http\Controllers\Registrar\TranscriptController;
use App\Http\Controllers\StudentPortal\GradesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Registrar Module Routes
|--------------------------------------------------------------------------
|
| Permission scopes:
|   students.enrollment.view   — read enrollment data
|   students.enrollment.manage — full enrollment CRUD + periods
|   students.policies.manage   — retention/honors policies
|   students.transcript.view   — view/download transcripts and report cards
|   students.transcript.manage — compute and lock grades
|   students.records.export    — generate COE, Good Moral, Clearance PDFs
|   students.promotion.process — year-end promotion workflow
|   students.analytics.view    — registrar analytics dashboard
|
*/

Route::middleware(['auth'])->group(function () {

    // ── Enrollment Applications (review & approve) ───────────────────────────

    Route::get('/registrar/enrollment-applications', [EnrollmentApplicationController::class, 'index'])
        ->name('registrar.enrollment-applications.index')
        ->middleware('permission:students.enrollment.manage');

    Route::get('/registrar/enrollment-applications/{enrollmentApplication}', [EnrollmentApplicationController::class, 'show'])
        ->name('registrar.enrollment-applications.show')
        ->middleware('permission:students.enrollment.manage');

    Route::post('/registrar/enrollment-applications/{enrollmentApplication}/approve', [EnrollmentApplicationController::class, 'approve'])
        ->name('registrar.enrollment-applications.approve')
        ->middleware('permission:students.enrollment.manage');

    Route::post('/registrar/enrollment-applications/{enrollmentApplication}/reject', [EnrollmentApplicationController::class, 'reject'])
        ->name('registrar.enrollment-applications.reject')
        ->middleware('permission:students.enrollment.manage');

    Route::post('/registrar/enrollment-applications/{enrollmentApplication}/reopen', [EnrollmentApplicationController::class, 'reopen'])
        ->name('registrar.enrollment-applications.reopen')
        ->middleware('permission:students.enrollment.manage');

    // ── Legacy Section Assignment (one-time import wizard) ───────────────────

    Route::get('/registrar/section-assignment', [SectionAssignmentController::class, 'index'])
        ->name('registrar.section-assignment.index')
        ->middleware('permission:students.enrollment.manage');

    Route::post('/registrar/section-assignment', [SectionAssignmentController::class, 'store'])
        ->name('registrar.section-assignment.store')
        ->middleware('permission:students.enrollment.manage');

    // ── Enrollment Management ─────────────────────────────────────────────────

    Route::get('/registrar/enrollment', [EnrollmentController::class, 'index'])
        ->name('registrar.enrollment.index')
        ->middleware('permission:students.enrollment.view');

    Route::get('/registrar/enrollment/search', [EnrollmentController::class, 'search'])
        ->name('registrar.enrollment.search')
        ->middleware('permission:students.enrollment.view');

    Route::get('/registrar/enrollment/section/{sectionId}/students', [EnrollmentController::class, 'sectionStudents'])
        ->name('registrar.enrollment.section-students')
        ->middleware('permission:students.enrollment.view');

    Route::post('/registrar/enrollment', [EnrollmentController::class, 'store'])
        ->name('registrar.enrollment.store')
        ->middleware('permission:students.enrollment.manage');

    Route::post('/registrar/enrollment/bulk', [EnrollmentController::class, 'bulkStore'])
        ->name('registrar.enrollment.bulk-store')
        ->middleware('permission:students.enrollment.manage');

    Route::put('/registrar/enrollment/{enrollment}', [EnrollmentController::class, 'update'])
        ->name('registrar.enrollment.update')
        ->middleware('permission:students.enrollment.manage');

    Route::delete('/registrar/enrollment/{enrollment}', [EnrollmentController::class, 'destroy'])
        ->name('registrar.enrollment.destroy')
        ->middleware('permission:students.enrollment.manage');

    // ── Enrollment Periods ────────────────────────────────────────────────────

    Route::get('/registrar/enrollment-periods', [EnrollmentPeriodController::class, 'index'])
        ->name('registrar.enrollment-periods.index')
        ->middleware('permission:students.enrollment.manage');

    Route::post('/registrar/enrollment-periods', [EnrollmentPeriodController::class, 'store'])
        ->name('registrar.enrollment-periods.store')
        ->middleware('permission:students.enrollment.manage');

    Route::put('/registrar/enrollment-periods/{period}', [EnrollmentPeriodController::class, 'update'])
        ->name('registrar.enrollment-periods.update')
        ->middleware('permission:students.enrollment.manage');

    Route::post('/registrar/enrollment-periods/{period}/toggle', [EnrollmentPeriodController::class, 'toggle'])
        ->name('registrar.enrollment-periods.toggle')
        ->middleware('permission:students.enrollment.manage');

    Route::delete('/registrar/enrollment-periods/{period}', [EnrollmentPeriodController::class, 'destroy'])
        ->name('registrar.enrollment-periods.destroy')
        ->middleware('permission:students.enrollment.manage');

    // ── Academic Policies (Retention / Honors) ────────────────────────────────

    Route::get('/registrar/academic-policies', [RetentionPolicyController::class, 'index'])
        ->name('registrar.academic-policies.index')
        ->middleware('permission:students.policies.manage');

    Route::post('/registrar/academic-policies', [RetentionPolicyController::class, 'store'])
        ->name('registrar.academic-policies.store')
        ->middleware('permission:students.policies.manage');

    Route::put('/registrar/academic-policies/{policy}', [RetentionPolicyController::class, 'update'])
        ->name('registrar.academic-policies.update')
        ->middleware('permission:students.policies.manage');

    Route::delete('/registrar/academic-policies/{policy}', [RetentionPolicyController::class, 'destroy'])
        ->name('registrar.academic-policies.destroy')
        ->middleware('permission:students.policies.manage');

    // ── Academic Transcripts ──────────────────────────────────────────────────

    Route::get('/registrar/transcripts', [TranscriptController::class, 'index'])
        ->name('registrar.transcript.index')
        ->middleware('permission:students.transcript.view');

    Route::get('/registrar/transcripts/{studentId}', [TranscriptController::class, 'show'])
        ->name('registrar.transcript.show')
        ->middleware('permission:students.transcript.view');

    Route::post('/registrar/transcripts/compute', [TranscriptController::class, 'compute'])
        ->name('registrar.transcript.compute')
        ->middleware('permission:students.transcript.manage');

    Route::post('/registrar/transcripts/bulk-compute', [TranscriptController::class, 'bulkCompute'])
        ->name('registrar.transcript.bulk-compute')
        ->middleware('permission:students.transcript.manage');

    Route::post('/registrar/transcripts/lock', [TranscriptController::class, 'lock'])
        ->name('registrar.transcript.lock')
        ->middleware('permission:students.transcript.manage');

    // ── Report Card PDF ───────────────────────────────────────────────────────

    Route::get('/registrar/students/{student}/report-card/{schoolYear}', [ReportCardController::class, 'download'])
        ->name('registrar.report-card')
        ->middleware('permission:students.transcript.view');

    // ── Official Documents ────────────────────────────────────────────────────

    Route::get('/registrar/students/{student}/documents/{type}/{schoolYear}', [StudentDocumentController::class, 'download'])
        ->name('registrar.student-document')
        ->middleware('permission:students.records.export')
        ->where('type', 'coe|good-moral|clearance');

    // ── Year-End Promotion ────────────────────────────────────────────────────

    Route::get('/registrar/promotion', [PromotionController::class, 'index'])
        ->name('registrar.promotion.index')
        ->middleware('permission:students.promotion.process');

    Route::get('/registrar/promotion/preview', [PromotionController::class, 'preview'])
        ->name('registrar.promotion.preview')
        ->middleware('permission:students.promotion.process');

    Route::post('/registrar/promotion/confirm', [PromotionController::class, 'confirm'])
        ->name('registrar.promotion.confirm')
        ->middleware('permission:students.promotion.process');

    // ── Analytics ─────────────────────────────────────────────────────────────

    Route::get('/registrar/analytics', [AnalyticsController::class, 'index'])
        ->name('registrar.analytics.index')
        ->middleware('permission:students.analytics.view');
});

// ── Student Portal — Grades (uses student.portal middleware, not auth) ────────

Route::prefix('student-portal')->name('student-portal.')->middleware('student.portal')->group(function () {
    Route::get('/grades', [GradesController::class, 'index'])->name('grades');
});
