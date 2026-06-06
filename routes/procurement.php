<?php

use App\Http\Controllers\Procurement\DisbursementVoucherController;
use App\Http\Controllers\Procurement\OblRequestController;
use App\Http\Controllers\Procurement\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement Routes — PR, ORS, DV
|--------------------------------------------------------------------------
|
| Permission scopes:
|   procurement.pr.*    — Purchase Requests
|   procurement.ors.*   — Obligation Request Status
|   procurement.dv.*    — Disbursement Vouchers
|
*/

Route::middleware(['web', 'auth', 'pshs.email'])
    ->group(function () {

        // ── Purchase Requests (PR) ────────────────────────────────────────────
        Route::middleware('permission:procurement.pr.view|procurement.pr.create')
            ->group(function () {
                Route::get('/procurements',
                    [PurchaseRequestController::class, 'index'])->name('procurements.index');
                Route::get('/procurements/{procurement}',
                    [PurchaseRequestController::class, 'show'])->name('procurements.show');
            });

        Route::middleware('permission:procurement.pr.create')->group(function () {
            Route::post('/procurements',
                [PurchaseRequestController::class, 'store'])->name('procurements.store');
            Route::put('/procurements/{procurement}',
                [PurchaseRequestController::class, 'update'])->name('procurements.update');
            Route::delete('/procurements/{procurement}',
                [PurchaseRequestController::class, 'destroy'])->name('procurements.destroy');
            Route::post('/procurements/{procurement}/submit',
                [PurchaseRequestController::class, 'submitForApproval'])->name('procurements.submit');
            Route::post('/procurements/{procurement}/items',
                [PurchaseRequestController::class, 'storeItem'])->name('procurements.items.store');
            Route::delete('/procurements/{procurement}/items/{item}',
                [PurchaseRequestController::class, 'destroyItem'])->name('procurements.items.destroy');
        });

        Route::post('/procurements/{procurement}/dc-sign',
            [PurchaseRequestController::class, 'dcSign'])
            ->name('procurements.dc-sign')
            ->middleware('permission:procurement.pr.dc_sign');

        Route::post('/procurements/{procurement}/bo-initial',
            [PurchaseRequestController::class, 'boInitial'])
            ->name('procurements.bo-initial')
            ->middleware('permission:procurement.pr.bo_initial');

        Route::post('/procurements/{procurement}/supplemental-ocd-sign',
            [PurchaseRequestController::class, 'supplementalOcdSign'])
            ->name('procurements.supplemental-ocd-sign')
            ->middleware('permission:procurement.pr.ocd_sign');

        Route::post('/procurements/{procurement}/assign-number',
            [PurchaseRequestController::class, 'assignNumber'])
            ->name('procurements.assign-number')
            ->middleware('permission:procurement.pr.number');

        Route::post('/procurements/{procurement}/ocd-sign',
            [PurchaseRequestController::class, 'ocdSign'])
            ->name('procurements.ocd-sign')
            ->middleware('permission:procurement.pr.ocd_sign');

        Route::post('/procurements/{procurement}/reject',
            [PurchaseRequestController::class, 'reject'])
            ->name('procurements.reject')
            ->middleware('permission:procurement.pr.dc_sign|procurement.pr.number|procurement.pr.ocd_sign|procurement.pr.bo_initial');

        // ── ORS ───────────────────────────────────────────────────────────────
        Route::middleware('permission:procurement.ors.view|procurement.ors.create')
            ->group(function () {
                Route::get('/ors',
                    [OblRequestController::class, 'index'])->name('ors.index');
                Route::get('/ors/{ors}',
                    [OblRequestController::class, 'show'])->name('ors.show');
            });

        Route::middleware('permission:procurement.ors.create')->group(function () {
            Route::post('/ors',
                [OblRequestController::class, 'store'])->name('ors.store');
            Route::post('/ors/{ors}/submit',
                [OblRequestController::class, 'submitForApproval'])->name('ors.submit');
            Route::post('/ors/{ors}/log',
                [OblRequestController::class, 'logAndForward'])->name('ors.log');
        });

        Route::post('/ors/{ors}/dc-sign',
            [OblRequestController::class, 'dcSign'])
            ->name('ors.dc-sign')
            ->middleware('permission:procurement.ors.dc_sign');

        Route::post('/ors/{ors}/bo-action',
            [OblRequestController::class, 'budgetOfficerAction'])
            ->name('ors.bo-action')
            ->middleware('permission:procurement.ors.budget_sign');

        Route::post('/ors/{ors}/bookkeeper-action',
            [OblRequestController::class, 'bookkeeperAction'])
            ->name('ors.bookkeeper-action')
            ->middleware('permission:procurement.ors.bookkeep');

        Route::post('/ors/{ors}/accountant-sign',
            [OblRequestController::class, 'accountantSign'])
            ->name('ors.accountant-sign')
            ->middleware('permission:procurement.ors.account');

        Route::post('/ors/{ors}/ocd-sign',
            [OblRequestController::class, 'ocdSign'])
            ->name('ors.ocd-sign')
            ->middleware('permission:procurement.ors.ocd_sign');

        Route::post('/ors/{ors}/canvaser-receive',
            [OblRequestController::class, 'canvaserReceive'])
            ->name('ors.canvaser-receive');

        // ── Disbursement Vouchers (DV) ────────────────────────────────────────
        Route::middleware('permission:procurement.dv.view|procurement.dv.create')
            ->group(function () {
                Route::get('/dv',
                    [DisbursementVoucherController::class, 'index'])->name('dv.index');
                Route::get('/dv/{dv}',
                    [DisbursementVoucherController::class, 'show'])->name('dv.show');
            });

        Route::middleware('permission:procurement.dv.create')->group(function () {
            Route::post('/dv',
                [DisbursementVoucherController::class, 'store'])->name('dv.store');
            Route::post('/dv/{dv}/prepare',
                [DisbursementVoucherController::class, 'prepareDv'])->name('dv.prepare');
            Route::post('/dv/{dv}/dc-elog',
                [DisbursementVoucherController::class, 'dcElog'])->name('dv.dc-elog');
            Route::post('/dv/{dv}/forward-bookkeeper',
                [DisbursementVoucherController::class, 'forwardToBookkeeper'])->name('dv.forward-bookkeeper');
        });

        Route::post('/dv/{dv}/delivery',
            [DisbursementVoucherController::class, 'recordDelivery'])
            ->name('dv.delivery')
            ->middleware('permission:procurement.dv.delivery');

        Route::post('/dv/{dv}/bookkeeper-action',
            [DisbursementVoucherController::class, 'bookkeeperAction'])
            ->name('dv.bookkeeper-action')
            ->middleware('permission:procurement.dv.bookkeep');

        Route::post('/dv/{dv}/accountant-action',
            [DisbursementVoucherController::class, 'accountantAction'])
            ->name('dv.accountant-action')
            ->middleware('permission:procurement.dv.account');

        Route::post('/dv/{dv}/ocd-sign',
            [DisbursementVoucherController::class, 'ocdSign'])
            ->name('dv.ocd-sign')
            ->middleware('permission:procurement.dv.ocd_sign');

        Route::post('/dv/{dv}/cashier-process',
            [DisbursementVoucherController::class, 'cashierProcess'])
            ->name('dv.cashier-process')
            ->middleware('permission:procurement.dv.cashier');

        Route::post('/dv/{dv}/ocd-payment-sign',
            [DisbursementVoucherController::class, 'ocdPaymentSign'])
            ->name('dv.ocd-payment-sign')
            ->middleware('permission:procurement.dv.ocd_sign');
    });
