<?php

use App\Http\Controllers\AMS\ActivityController;
use App\Http\Controllers\AMS\CertificateController;
use App\Http\Controllers\AMS\EvaluationController;
use App\Http\Controllers\AMS\MyActivityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AMS — Activity Management System
|--------------------------------------------------------------------------
|
| All routes require the activities.manage permission.
|
*/

Route::middleware(['web', 'auth', 'verified', 'permission:activities.manage|activities.view_all'])
    ->prefix('ams/activities')
    ->name('ams.activities.')
    ->group(function () {

        Route::get('/',             [ActivityController::class, 'index'])->name('index');
        Route::get('/create',       [ActivityController::class, 'create'])->name('create');
        Route::post('/',            [ActivityController::class, 'store'])->name('store');
        Route::get('/{activity}/edit', [ActivityController::class, 'edit'])->name('edit');
        Route::post('/{activity}',  [ActivityController::class, 'update'])->name('update');
        Route::delete('/{activity}',[ActivityController::class, 'destroy'])->name('destroy');

        // ── Co-Proponents ─────────────────────────────────────────────────────
        Route::post('/{activity}/co-proponents',
            [ActivityController::class, 'addCoProponent'])->name('co-proponents.add');
        Route::delete('/{activity}/co-proponents/{coProponent}',
            [ActivityController::class, 'removeCoProponent'])->name('co-proponents.remove');

        // ── Participants ──────────────────────────────────────────────────────
        Route::post('/{activity}/participants',
            [ActivityController::class, 'addParticipants'])->name('participants.add');
        Route::delete('/{activity}/participants/{participant}',
            [ActivityController::class, 'removeParticipant'])->name('participants.remove');
        Route::post('/{activity}/participants/{participant}/save-attendance',
            [ActivityController::class, 'saveEmployeeAttendance'])->name('participants.save-attendance');

        // ── Student Attendance (section roster) ───────────────────────────────
        Route::get('/{activity}/participants/{participant}/students',
            [ActivityController::class, 'sectionStudents'])->name('participants.students');
        Route::post('/{activity}/participants/{participant}/save-section-attendance',
            [ActivityController::class, 'saveSectionAttendance'])->name('participants.save-section-attendance');

        // ── Evaluation links ──────────────────────────────────────────────────
        Route::post('/{activity}/participants/send-evaluation-links',
            [ActivityController::class, 'sendEvaluationLinks'])->name('participants.send-evaluation-links');

        // ── Certificates ─────────────────────────────────────────────────────
        Route::post('/{activity}/certificates/generate',
            [CertificateController::class, 'generate'])->name('certificates.generate');
        Route::get('/{activity}/participants/{participant}/certificate',
            [CertificateController::class, 'downloadParticipant'])->name('certificates.download.participant');
        Route::get('/{activity}/students/{attendance}/certificate',
            [CertificateController::class, 'downloadStudent'])->name('certificates.download.student');

        // ── Show (registered last so /{activity} never shadows specific routes)
        Route::get('/{activity}',   [ActivityController::class, 'show'])->name('show');

    });

// ── My Activities (authenticated, any user) ───────────────────────────────────
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('ams/my-activities')
    ->name('ams.my-activities.')
    ->group(function () {
        Route::get('/',                        [MyActivityController::class, 'index'])->name('index');
        Route::get('/{activity}/certificate',  [MyActivityController::class, 'downloadCertificate'])->name('certificate');
        Route::get('/{activity}',              [MyActivityController::class, 'show'])->name('show');
    });

// ── Public certificate verification (no auth) ─────────────────────────────────
Route::middleware(['web'])
    ->prefix('ams/verify')
    ->name('ams.certificates.')
    ->group(function () {
        Route::get('/{activity}/{hash}', [CertificateController::class, 'verify'])->name('verify');
    });

// ── Public evaluation form (no auth) ──────────────────────────────────────────
Route::middleware(['web'])
    ->prefix('ams/activities')
    ->name('ams.activities.')
    ->group(function () {
        Route::get('/{activity}/evaluate/{hash}',  [EvaluationController::class, 'show'])->name('evaluate.show');
        Route::post('/{activity}/evaluate/{hash}', [EvaluationController::class, 'store'])->name('evaluate.store');
    });
