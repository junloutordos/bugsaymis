<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Property\PropertyItemController;
use App\Http\Controllers\Property\IcsController;
use App\Http\Controllers\Property\ParController;
use App\Http\Controllers\Property\PropertyTransferController;
use App\Http\Controllers\Property\PropertyReportController;
use App\Http\Controllers\Property\WorkOrderController;
use App\Http\Controllers\Property\DisposalController;

Route::middleware(['auth', 'permission:property.view'])->prefix('property')->name('property.')->group(function () {

    // Property Items
    Route::get('/items', [PropertyItemController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [PropertyItemController::class, 'show'])->name('items.show');

    Route::middleware('permission:property.manage')->group(function () {
        Route::post('/items', [PropertyItemController::class, 'store'])->name('items.store');
        Route::put('/items/{item}', [PropertyItemController::class, 'update'])->name('items.update');
        Route::post('/categories', [PropertyItemController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [PropertyItemController::class, 'updateCategory'])->name('categories.update');
    });

    // ICS
    Route::get('/ics', [IcsController::class, 'index'])->name('ics.index');
    Route::get('/ics/create', [IcsController::class, 'create'])->name('ics.create');
    Route::get('/ics/{ics}', [IcsController::class, 'show'])->name('ics.show');
    Route::get('/ics/{ics}/pdf', [IcsController::class, 'printPdf'])->name('ics.pdf');

    Route::middleware('permission:property.manage')->group(function () {
        Route::post('/ics', [IcsController::class, 'store'])->name('ics.store');
    });

    // PAR
    Route::get('/par', [ParController::class, 'index'])->name('par.index');
    Route::get('/par/create', [ParController::class, 'create'])->name('par.create');
    Route::get('/par/{par}', [ParController::class, 'show'])->name('par.show');
    Route::get('/par/{par}/pdf', [ParController::class, 'printPdf'])->name('par.pdf');

    Route::middleware('permission:property.manage')->group(function () {
        Route::post('/par', [ParController::class, 'store'])->name('par.store');
    });

    // Transfers
    Route::get('/transfers', [PropertyTransferController::class, 'index'])->name('transfers.index');

    Route::middleware('permission:property.transfer')->group(function () {
        Route::post('/transfers', [PropertyTransferController::class, 'store'])->name('transfers.store');
        Route::post('/transfers/{transfer}/complete', [PropertyTransferController::class, 'complete'])->name('transfers.complete');
        Route::post('/transfers/{transfer}/cancel', [PropertyTransferController::class, 'cancel'])->name('transfers.cancel');
    });

    // Reports
    Route::middleware('permission:property.reports')->group(function () {
        Route::get('/reports', [PropertyReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/rpci-pdf', [PropertyReportController::class, 'rpciPdf'])->name('reports.rpci-pdf');
        Route::get('/reports/rpcppe-pdf', [PropertyReportController::class, 'rpcppePdf'])->name('reports.rpcppe-pdf');
        Route::get('/reports/depreciation-schedule', [PropertyReportController::class, 'depreciationSchedule'])->name('reports.depreciation-schedule');
    });

    // Work Orders
    Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');

    Route::middleware('permission:work-orders.manage')->group(function () {
        Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::patch('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    });

    // Disposal
    Route::middleware('permission:property.dispose')->group(function () {
        Route::get('/disposal', [DisposalController::class, 'index'])->name('disposal.index');
        Route::post('/disposal/bsr', [DisposalController::class, 'storeBsr'])->name('disposal.bsr.store');
        Route::post('/disposal/bsr/{bsr}/approve', [DisposalController::class, 'approveBsr'])->name('disposal.bsr.approve');
        Route::post('/disposal', [DisposalController::class, 'storeDisposal'])->name('disposal.store');
        Route::post('/disposal/{disposal}/approve', [DisposalController::class, 'approveDisposal'])->name('disposal.approve');
        Route::post('/disposal/{disposal}/complete', [DisposalController::class, 'completeDisposal'])->name('disposal.complete');
    });
});
