<?php

use App\Http\Controllers\SALN\HRSalnController;
use App\Http\Controllers\SALN\SalnController;
use App\Http\Controllers\SALN\SalnReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SALN Module Routes
|--------------------------------------------------------------------------
|
| Grouped into three role-scoped sections:
|
|  1. Employee   /saln            — create, manage own SALN, submit
|  2. Committee  /saln/review     — review, approve, return submitted SALNs
|  3. HR Office  /saln/hr         — file, reports, compliance
|
| PDF export lives at GET /saln/{saln}/pdf (available to owner + HR).
|
| IMPORTANT: The wildcard routes (GET /saln/{saln} and /saln/{saln}/pdf)
| are registered LAST so they never shadow specific routes like /saln/create
| or /saln/hr/... (Laravel matches routes in insertion order).
|
*/

// ══════════════════════════════════════════════════════════════════════════════
// 1. Employee — create & manage own SALN
// ══════════════════════════════════════════════════════════════════════════════
Route::middleware(['web', 'auth', 'verified', 'permission:saln.create'])
    ->prefix('saln')
    ->name('saln.')
    ->group(function () {

        // ── SALN Record CRUD ──────────────────────────────────────────────────
        Route::get('/',             [SalnController::class, 'index'])->name('index');
        Route::get('/create',       [SalnController::class, 'create'])->name('create');
        Route::post('/',            [SalnController::class, 'store'])->name('store');
        Route::put('/{saln}',       [SalnController::class, 'update'])->name('update');
        Route::delete('/{saln}',    [SalnController::class, 'destroy'])->name('destroy');

        // ── Submit for review ─────────────────────────────────────────────────
        Route::post('/{saln}/submit', [SalnController::class, 'submit'])->name('submit');

        // ── Section: Real Properties ──────────────────────────────────────────
        Route::post('/{saln}/real-properties',
            [SalnController::class, 'storeRealProperty'])
            ->name('real-properties.store');
        Route::put('/{saln}/real-properties/{property}',
            [SalnController::class, 'updateRealProperty'])
            ->name('real-properties.update');
        Route::delete('/{saln}/real-properties/{property}',
            [SalnController::class, 'destroyRealProperty'])
            ->name('real-properties.destroy');

        // ── Section: Personal Properties ──────────────────────────────────────
        Route::post('/{saln}/personal-properties',
            [SalnController::class, 'storePersonalProperty'])
            ->name('personal-properties.store');
        Route::put('/{saln}/personal-properties/{property}',
            [SalnController::class, 'updatePersonalProperty'])
            ->name('personal-properties.update');
        Route::delete('/{saln}/personal-properties/{property}',
            [SalnController::class, 'destroyPersonalProperty'])
            ->name('personal-properties.destroy');

        // ── Section: Liabilities ──────────────────────────────────────────────
        Route::post('/{saln}/liabilities',
            [SalnController::class, 'storeLiability'])
            ->name('liabilities.store');
        Route::put('/{saln}/liabilities/{liability}',
            [SalnController::class, 'updateLiability'])
            ->name('liabilities.update');
        Route::delete('/{saln}/liabilities/{liability}',
            [SalnController::class, 'destroyLiability'])
            ->name('liabilities.destroy');

        // ── Section: Business Interests & Financial Connections ───────────────
        Route::post('/{saln}/business-interests',
            [SalnController::class, 'storeBusinessInterest'])
            ->name('business-interests.store');
        Route::put('/{saln}/business-interests/{interest}',
            [SalnController::class, 'updateBusinessInterest'])
            ->name('business-interests.update');
        Route::delete('/{saln}/business-interests/{interest}',
            [SalnController::class, 'destroyBusinessInterest'])
            ->name('business-interests.destroy');

        // ── Section: Relatives in Government Service ──────────────────────────
        Route::post('/{saln}/relatives',
            [SalnController::class, 'storeRelative'])
            ->name('relatives.store');
        Route::put('/{saln}/relatives/{relative}',
            [SalnController::class, 'updateRelative'])
            ->name('relatives.update');
        Route::delete('/{saln}/relatives/{relative}',
            [SalnController::class, 'destroyRelative'])
            ->name('relatives.destroy');

        // ── Section: Unmarried Children Below 18 ─────────────────────────────
        Route::post('/{saln}/children',
            [SalnController::class, 'storeChild'])
            ->name('children.store');
        Route::put('/{saln}/children/{child}',
            [SalnController::class, 'updateChild'])
            ->name('children.update');
        Route::delete('/{saln}/children/{child}',
            [SalnController::class, 'destroyChild'])
            ->name('children.destroy');

    });

// ══════════════════════════════════════════════════════════════════════════════
// 2. SALN Committee — review workflow
// ══════════════════════════════════════════════════════════════════════════════
Route::middleware(['web', 'auth', 'verified', 'permission:saln.review'])
    ->prefix('saln/review')
    ->name('saln.review.')
    ->group(function () {

        // Queue of submitted / under-review SALNs
        Route::get('/',              [SalnReviewController::class, 'index'])->name('index');

        // Open a SALN for review (auto-transitions submitted → under_review)
        Route::get('/{saln}',        [SalnReviewController::class, 'show'])->name('show');

        // Approve
        Route::post('/{saln}/approve', [SalnReviewController::class, 'approve'])
            ->middleware('permission:saln.approve')
            ->name('approve');

        // Return for revision (requires remarks)
        Route::post('/{saln}/return',  [SalnReviewController::class, 'return'])
            ->name('return');
    });

// ══════════════════════════════════════════════════════════════════════════════
// 3. HR Office — filing & reports
// ══════════════════════════════════════════════════════════════════════════════
Route::middleware(['web', 'auth', 'verified', 'permission:saln.view_all'])
    ->prefix('saln/hr')
    ->name('saln.hr.')
    ->group(function () {

        // All SALN records — filterable by year, status, employee
        Route::get('/',                              [HRSalnController::class, 'index'])
            ->name('index');

        // Mark approved SALN as officially filed
        Route::post('/{saln}/file',                  [HRSalnController::class, 'file'])
            ->middleware('permission:saln.file')
            ->name('file');

        // Annual compliance report
        Route::get('/reports/annual',                [HRSalnController::class, 'annualReport'])
            ->name('reports.annual');

        // Per-employee multi-year history
        Route::get('/employees/{user}/history',      [HRSalnController::class, 'employeeHistory'])
            ->name('employee');
    });

// ══════════════════════════════════════════════════════════════════════════════
// 4. SALN show + PDF — registered LAST so the wildcard /{saln} never shadows
//    specific routes like /saln/create or /saln/hr/... above.
//    Accessible to the owner (saln.create), HR (saln.view_all), or committee (saln.review).
// ══════════════════════════════════════════════════════════════════════════════
Route::middleware(['web', 'auth', 'verified', 'permission:saln.create|saln.view_all|saln.review'])
    ->prefix('saln')
    ->name('saln.')
    ->group(function () {
        Route::get('/{saln}',     [SalnController::class, 'show'])->name('show');
        Route::get('/{saln}/pdf', \App\Http\Controllers\SALN\SalnPdfController::class)->name('pdf');
    });
