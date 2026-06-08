<?php

use App\Http\Controllers\PPMP\PPMPAppController;
use App\Http\Controllers\PPMP\PPMPCatalogueController;
use App\Http\Controllers\PPMP\PPMPController;
use App\Http\Controllers\PPMP\PPMPDashboardController;
use App\Http\Controllers\PPMP\PPMPExportController;
use App\Http\Controllers\PPMP\PPMPItemController;
use App\Http\Controllers\PPMP\PPMPSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PPMP Module Routes
|--------------------------------------------------------------------------
|
| Project Procurement Management Plan — GPPB-compliant procurement planning.
|
| Full workflow:
|   1. Unit staff creates/submits unit PPMP → Division Chief
|   2. Division Chief reviews unit PPMPs → consolidates into Division PPMP → submits to Property Officer
|   3. Property Officer reviews Division PPMPs → consolidates into Property PPMP → submits to Budget Officer
|   4. Budget Officer evaluates → submits to OCD
|   5. OCD approves → returns approved to Budget Officer
|   6. Budget Officer submits final PPMP/APP-CSE to DBM
|
*/

Route::middleware(['web', 'auth', 'pshs.email'])
    ->prefix('ppmp')
    ->name('ppmp.')
    ->group(function () {

        // ── Part I Catalogue (admin/consolidate) ──────────────────────────────
        Route::middleware('permission:ppmp.consolidate')->group(function () {
            Route::get('/catalogue', [PPMPCatalogueController::class, 'index'])->name('catalogue.index');
            Route::post('/catalogue/upload', [PPMPCatalogueController::class, 'upload'])->name('catalogue.upload');
        });
        Route::get('/catalogue/search', [PPMPCatalogueController::class, 'search'])->name('catalogue.search');

        // ── Dashboard (view_all only) ─────────────────────────────────────────
        Route::get('/dashboard', [PPMPDashboardController::class, 'index'])
            ->name('dashboard')
            ->middleware('permission:ppmp.view_all');

        // ── Settings ──────────────────────────────────────────────────────────
        Route::get('/settings', [PPMPSettingsController::class, 'index'])
            ->name('settings')
            ->middleware('permission:ppmp.consolidate');
        Route::put('/settings', [PPMPSettingsController::class, 'update'])
            ->name('settings.update')
            ->middleware('permission:ppmp.consolidate');

        // ── Stage 2: Division Chief consolidation ─────────────────────────────
        Route::post('/division-consolidate', [PPMPController::class, 'divisionConsolidate'])
            ->name('division_consolidate')
            ->middleware('permission:ppmp.division_review');

        // ── Stage 3: Property Officer consolidation ───────────────────────────
        Route::post('/property-consolidate', [PPMPController::class, 'propertyConsolidate'])
            ->name('property_consolidate')
            ->middleware('permission:ppmp.property_officer_review');

        // ── APP Consolidation (Procurement Officer) ───────────────────────────
        Route::middleware('permission:ppmp.consolidate')->group(function () {
            Route::get('/app', [PPMPAppController::class, 'index'])->name('app.index');
            Route::post('/app/consolidate', [PPMPAppController::class, 'consolidate'])->name('app.consolidate');
            Route::get('/app/export/excel', [PPMPAppController::class, 'excel'])->name('app.export.excel');
        });

        // ── PPMP CRUD ─────────────────────────────────────────────────────────
        Route::middleware('permission:ppmp.create|ppmp.view_all|ppmp.division_review|ppmp.property_officer_review|ppmp.budget_officer_review|ppmp.approve')->group(function () {
            Route::get('/', [PPMPController::class, 'index'])->name('index');
            Route::get('/create', [PPMPController::class, 'create'])->name('create');
            Route::post('/', [PPMPController::class, 'store'])->name('store');
            Route::get('/{ppmp}', [PPMPController::class, 'show'])->name('show');
            Route::put('/{ppmp}', [PPMPController::class, 'update'])->name('update');
            Route::delete('/{ppmp}', [PPMPController::class, 'destroy'])->name('destroy');
        });

        // ── Item management ───────────────────────────────────────────────────
        Route::middleware('permission:ppmp.create')->group(function () {
            Route::post('/{ppmp}/items', [PPMPItemController::class, 'store'])->name('items.store');
            Route::put('/{ppmp}/items/{item}', [PPMPItemController::class, 'update'])->name('items.update');
            Route::delete('/{ppmp}/items/{item}', [PPMPItemController::class, 'destroy'])->name('items.destroy');
        });

        // ── Workflow ──────────────────────────────────────────────────────────
        Route::post('/{ppmp}/validate', [PPMPController::class, 'validatePpmp'])->name('validate');
        Route::post('/{ppmp}/submit', [PPMPController::class, 'submit'])->name('submit');

        // Stage 2: Division Chief reviews unit PPMP
        Route::post('/{ppmp}/division-review', [PPMPController::class, 'divisionReview'])
            ->name('division_review')
            ->middleware('permission:ppmp.division_review');

        // Stage 3: Property Officer reviews division PPMP
        Route::post('/{ppmp}/property-officer-review', [PPMPController::class, 'propertyOfficerReview'])
            ->name('property_officer_review')
            ->middleware('permission:ppmp.property_officer_review');

        // Stage 4: Budget Officer reviews property PPMP → sends to OCD
        Route::post('/{ppmp}/budget-officer-review', [PPMPController::class, 'budgetOfficerReview'])
            ->name('budget_officer_review')
            ->middleware('permission:ppmp.budget_officer_review');

        // Stage 5: OCD final approval / return
        Route::post('/{ppmp}/ocd-approve', [PPMPController::class, 'ocdApprove'])
            ->name('ocd_approve')
            ->middleware('permission:ppmp.approve');
        Route::post('/{ppmp}/ocd-return', [PPMPController::class, 'ocdReturn'])
            ->name('ocd_return')
            ->middleware('permission:ppmp.approve');

        // Stage 6: Budget Officer submits to DBM
        Route::post('/{ppmp}/submit-to-dbm', [PPMPController::class, 'submitToDbm'])
            ->name('submit_to_dbm')
            ->middleware('permission:ppmp.budget_officer_review');

        // Legacy BAC review
        Route::post('/{ppmp}/bac-review', [PPMPController::class, 'bacReview'])
            ->name('bac_review')
            ->middleware('permission:ppmp.bac_review');

        // Legacy approve/return (for pending_head/submitted/pending_bac records)
        Route::post('/{ppmp}/approve', [PPMPController::class, 'approve'])
            ->name('approve')
            ->middleware('permission:ppmp.approve');
        Route::post('/{ppmp}/return', [PPMPController::class, 'returnPpmp'])
            ->name('return')
            ->middleware('permission:ppmp.approve');

        // ── Export ────────────────────────────────────────────────────────────
        Route::middleware('permission:ppmp.export')->group(function () {
            Route::get('/{ppmp}/export/pdf',   [PPMPExportController::class, 'pdf'])->name('export.pdf');
            Route::get('/{ppmp}/export/excel', [PPMPExportController::class, 'excel'])->name('export.excel');
        });
    });
