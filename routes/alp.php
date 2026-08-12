<?php

use App\Http\Controllers\ALP\AlpController;
use App\Http\Controllers\ALP\AlpPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:alp.view|alp.manage|alp.advise|alp.coordinate|alp.registrar-certify|alp.approve|alp.reports|alp.audit'])
    ->prefix('cid/alp')
    ->name('alp.')
    ->group(function () {
        Route::get('/', [AlpController::class, 'index'])->name('index');
        Route::get('/members', [AlpController::class, 'membersIndex'])->name('members.index');
        Route::get('/unassigned', [AlpController::class, 'unassignedIndex'])->name('unassigned.index');
        Route::get('/members.pdf', [AlpPdfController::class, 'membersList'])->name('members.pdf');
        Route::get('/unassigned.pdf', [AlpPdfController::class, 'unassignedList'])->name('unassigned.pdf');
        Route::post('/sync', [AlpController::class, 'sync'])->name('sync');
        Route::get('/cycles/{cycle}', [AlpController::class, 'show'])->name('cycles.show');
        Route::put('/cycles/{cycle}/program', [AlpController::class, 'updateProgram'])->name('program.update');
        Route::post('/cycles/{cycle}/transition', [AlpController::class, 'transition'])->name('cycles.transition');
        Route::get('/cycles/{cycle}/package.pdf', [AlpPdfController::class, 'package'])->name('cycles.package');
        Route::get('/cycles/{cycle}/students/search', [AlpController::class, 'searchStudents'])->name('students.search');
        Route::post('/cycles/{cycle}/members', [AlpController::class, 'storeMembers'])->name('members.store');
        Route::delete('/cycles/{cycle}/members/{membership}', [AlpController::class, 'destroyMember'])->name('members.destroy');
        Route::put('/cycles/{cycle}/members/{membership}/consent', [AlpController::class, 'updateConsent'])->name('members.consent');
        Route::put('/cycles/{cycle}/members/{membership}/accountability', [AlpController::class, 'updateAccountability'])->name('members.accountability');
        Route::get('/cycles/{cycle}/members/{membership}/certificate.pdf', [AlpPdfController::class, 'certificate'])->name('members.certificate');
        Route::post('/cycles/{cycle}/officers', [AlpController::class, 'storeOfficer'])->name('officers.store');
        Route::delete('/cycles/{cycle}/officers/{officer}', [AlpController::class, 'destroyOfficer'])->name('officers.destroy');
        Route::post('/cycles/{cycle}/officers/{officer}/certify', [AlpController::class, 'certifyOfficer'])->name('officers.certify');
        Route::put('/cycles/{cycle}/documents/{document}', [AlpController::class, 'saveDocument'])->name('documents.update');
        Route::get('/cycles/{cycle}/documents/{document}.pdf', [AlpPdfController::class, 'document'])->name('documents.pdf');
        Route::post('/cycles/{cycle}/activities', [AlpController::class, 'storeActivity'])->name('activities.store');
        Route::put('/cycles/{cycle}/activities/{activity}', [AlpController::class, 'updateActivity'])->name('activities.update');
        Route::post('/cycles/{cycle}/activities/{activity}/action', [AlpController::class, 'activityAction'])->name('activities.action');
        Route::post('/cycles/{cycle}/sessions', [AlpController::class, 'storeSession'])->name('sessions.store');
        Route::post('/cycles/{cycle}/sessions/{session}/attendance', [AlpController::class, 'saveAttendance'])->name('attendance.save');
        Route::get('/cycles/{cycle}/sessions/{session}/attendance.pdf', [AlpPdfController::class, 'attendance'])->name('attendance.pdf');
        Route::post('/cycles/{cycle}/finances', [AlpController::class, 'storeFinance'])->name('finances.store');
        Route::delete('/cycles/{cycle}/finances/{entry}', [AlpController::class, 'destroyFinance'])->name('finances.destroy');
        Route::post('/cycles/{cycle}/reports', [AlpController::class, 'storeReport'])->name('reports.store');
        Route::post('/cycles/{cycle}/reports/{report}/approve', [AlpController::class, 'approveReport'])->name('reports.approve');
        Route::get('/cycles/{cycle}/reports/{report}.pdf', [AlpPdfController::class, 'report'])->name('reports.pdf');
        Route::get('/cycles/{cycle}/files/{fileId}', [AlpController::class, 'file'])
            ->where('fileId', '[A-Za-z0-9_.=-]+')->name('files.show');
    });
