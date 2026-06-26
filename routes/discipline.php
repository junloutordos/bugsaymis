<?php

use App\Http\Controllers\Discipline\ConfiscatedItemController;
use App\Http\Controllers\Discipline\DisciplineCaseController;
use App\Http\Controllers\Discipline\DisciplineOffenseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Discipline (SDO) Routes
|--------------------------------------------------------------------------
|
| Permission scopes:
|   discipline.file    — file an Anecdotal Report (any faculty/staff)
|   discipline.view    — view cases
|   discipline.manage  — receive / review / resolve, catalog, confiscation
|
*/

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('discipline')
    ->name('discipline.')
    ->group(function () {

        // ── Cases ──────────────────────────────────────────────────────────────
        Route::middleware('permission:discipline.file')->group(function () {
            Route::get('/cases/create', [DisciplineCaseController::class, 'create'])->name('cases.create');
            Route::post('/cases', [DisciplineCaseController::class, 'store'])->name('cases.store');
            Route::get('/cases/students/search', [DisciplineCaseController::class, 'searchStudents'])->name('cases.students.search');
            Route::post('/cases/{case}/attachments', [DisciplineCaseController::class, 'uploadAttachment'])->name('cases.attachments.store');
            Route::delete('/cases/{case}/attachments/{attachment}', [DisciplineCaseController::class, 'deleteAttachment'])->name('cases.attachments.destroy');
        });

        Route::middleware('permission:discipline.view|discipline.manage')->group(function () {
            Route::get('/cases', [DisciplineCaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/{case}', [DisciplineCaseController::class, 'show'])->name('cases.show');
            Route::get('/cases/{case}/pdf', [DisciplineCaseController::class, 'pdf'])->name('cases.pdf');
            Route::get('/cases/{case}/attachments/{attachment}', [DisciplineCaseController::class, 'downloadAttachment'])->name('cases.attachments.show');
        });

        // Cancel — filer OR officer (controller enforces ownership)
        Route::middleware('permission:discipline.file|discipline.manage')->group(function () {
            Route::put('/cases/{case}/cancel', [DisciplineCaseController::class, 'cancel'])->name('cases.cancel');
        });

        // SDO-only case actions
        Route::middleware('permission:discipline.manage')->group(function () {
            Route::put('/cases/{case}/receive', [DisciplineCaseController::class, 'receive'])->name('cases.receive');
            Route::put('/cases/{case}/review', [DisciplineCaseController::class, 'review'])->name('cases.review');
            Route::put('/cases/{case}/resolve', [DisciplineCaseController::class, 'resolve'])->name('cases.resolve');
            Route::post('/cases/{case}/interventions', [DisciplineCaseController::class, 'addIntervention'])->name('cases.interventions.store');

            // ── Offense Catalog ──────────────────────────────────────────────────
            Route::get('/offenses', [DisciplineOffenseController::class, 'index'])->name('offenses.index');
            Route::post('/offenses', [DisciplineOffenseController::class, 'store'])->name('offenses.store');
            Route::put('/offenses/{offense}', [DisciplineOffenseController::class, 'update'])->name('offenses.update');
            Route::delete('/offenses/{offense}', [DisciplineOffenseController::class, 'destroy'])->name('offenses.destroy');

            // ── Confiscated Items ────────────────────────────────────────────────
            Route::get('/confiscated', [ConfiscatedItemController::class, 'index'])->name('confiscated.index');
            Route::get('/confiscated/students/search', [ConfiscatedItemController::class, 'searchStudents'])->name('confiscated.students.search');
            Route::post('/confiscated', [ConfiscatedItemController::class, 'store'])->name('confiscated.store');
            Route::put('/confiscated/{item}/claim', [ConfiscatedItemController::class, 'claim'])->name('confiscated.claim');
            Route::put('/confiscated/{item}/dispose', [ConfiscatedItemController::class, 'dispose'])->name('confiscated.dispose');
            Route::get('/confiscated/{item}/pdf', [ConfiscatedItemController::class, 'pdf'])->name('confiscated.pdf');
        });
    });
