<?php

use App\Http\Controllers\Procurement\DisbursementVoucherController;
use App\Http\Controllers\Procurement\OblRequestController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseRequestController;
use App\Http\Controllers\Procurement\RfqController;
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

        Route::get('/procurements/{procurement}/print',
            [PurchaseRequestController::class, 'print'])
            ->name('procurements.print')
            ->middleware('permission:procurement.pr.view|procurement.pr.create');

        Route::get('/procurements/{procurement}/market-study',
            [PurchaseRequestController::class, 'downloadMarketStudy'])
            ->name('procurements.market-study')
            ->middleware('permission:procurement.pr.view|procurement.pr.create');

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

        Route::get('/ors/{ors}/print',
            [OblRequestController::class, 'print'])
            ->name('ors.print')
            ->middleware('permission:procurement.ors.view|procurement.ors.create');

        // ── Purchase Orders (PO) ─────────────────────────────────────────────
        Route::middleware('permission:procurement.po.view|procurement.po.create|procurement.po.review|procurement.po.sign')
            ->group(function () {
                Route::get('/po', [PurchaseOrderController::class, 'index'])->name('po.index');
                Route::get('/po/{po}', [PurchaseOrderController::class, 'show'])->name('po.show');
            });

        Route::middleware('permission:procurement.po.create')->group(function () {
            Route::post('/po', [PurchaseOrderController::class, 'store'])->name('po.store');
            Route::post('/po/{po}/items', [PurchaseOrderController::class, 'storeItem'])->name('po.store-item');
            Route::delete('/po/{po}/items/{item}', [PurchaseOrderController::class, 'destroyItem'])->name('po.destroy-item');
            Route::post('/po/{po}/submit', [PurchaseOrderController::class, 'submit'])->name('po.submit');
            Route::post('/po/{po}/cancel', [PurchaseOrderController::class, 'cancel'])->name('po.cancel');
        });

        Route::post('/po/{po}/procurement-review', [PurchaseOrderController::class, 'procurementReview'])
            ->name('po.procurement-review')
            ->middleware('permission:procurement.po.review');

        Route::post('/po/{po}/ocd-sign', [PurchaseOrderController::class, 'ocdSign'])
            ->name('po.ocd-sign')
            ->middleware('permission:procurement.po.sign');

        Route::get('/po/{po}/print', [PurchaseOrderController::class, 'print'])
            ->name('po.print')
            ->middleware('permission:procurement.po.view|procurement.po.create|procurement.po.review|procurement.po.sign');

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

        Route::get('/dv/{dv}/print',
            [DisbursementVoucherController::class, 'print'])
            ->name('dv.print')
            ->middleware('permission:procurement.dv.view|procurement.dv.create');

        // ── Request for Quotation (RFQ) ──────────────────────────────────────
        Route::middleware('permission:procurement.rfq.view|procurement.rfq.create|procurement.rfq.evaluate|procurement.rfq.award')
            ->group(function () {
                Route::get('/rfq', [RfqController::class, 'index'])->name('rfq.index');
                Route::get('/rfq/{rfq}', [RfqController::class, 'show'])->name('rfq.show');
            });

        Route::middleware('permission:procurement.rfq.create')->group(function () {
            Route::post('/rfq', [RfqController::class, 'store'])->name('rfq.store');
            Route::put('/rfq/{rfq}', [RfqController::class, 'update'])->name('rfq.update');
            Route::post('/rfq/{rfq}/suppliers', [RfqController::class, 'addSupplier'])->name('rfq.suppliers.add');
            Route::delete('/rfq/{rfq}/suppliers/{supplier}', [RfqController::class, 'removeSupplier'])->name('rfq.suppliers.remove');
            Route::post('/rfq/{rfq}/open', [RfqController::class, 'open'])->name('rfq.open');
            Route::post('/rfq/{rfq}/suppliers/{supplier}/sent', [RfqController::class, 'markSent'])->name('rfq.suppliers.sent');
        });

        Route::post('/rfq/{rfq}/suppliers/{supplier}/quotation', [RfqController::class, 'uploadQuotation'])
            ->name('rfq.suppliers.upload')
            ->middleware('permission:procurement.rfq.upload|procurement.rfq.create');

        Route::post('/rfq/{rfq}/suppliers/{supplier}/quotations', [RfqController::class, 'saveQuotations'])
            ->name('rfq.suppliers.quotations')
            ->middleware('permission:procurement.rfq.evaluate|procurement.rfq.create');

        Route::post('/rfq/{rfq}/close', [RfqController::class, 'close'])
            ->name('rfq.close')
            ->middleware('permission:procurement.rfq.evaluate');

        Route::post('/rfq/{rfq}/award', [RfqController::class, 'award'])
            ->name('rfq.award')
            ->middleware('permission:procurement.rfq.award');

        Route::post('/rfq/{rfq}/generate-pos', [RfqController::class, 'generatePos'])
            ->name('rfq.generate-pos')
            ->middleware('permission:procurement.rfq.award');

        Route::get('/rfq/{rfq}/suppliers/{supplier}/download', [RfqController::class, 'downloadQuotation'])
            ->name('rfq.suppliers.download')
            ->middleware('permission:procurement.rfq.view|procurement.rfq.evaluate');

        Route::get('/rfq/{rfq}/print', [RfqController::class, 'printRfq'])
            ->name('rfq.print')
            ->middleware('permission:procurement.rfq.view|procurement.rfq.create');

        Route::get('/rfq/{rfq}/aoq', [RfqController::class, 'printAoq'])
            ->name('rfq.aoq')
            ->middleware('permission:procurement.rfq.view|procurement.rfq.evaluate');

        Route::get('/rfq/{rfq}/suppliers/{supplier}/noa', [RfqController::class, 'printNoa'])
            ->name('rfq.noa')
            ->middleware('permission:procurement.rfq.view|procurement.rfq.award');
    });
